#!/usr/bin/env python3
"""Compare actual passing PHP-FPM HTTP requests on one runner, in matched pairs."""
import json
import os
import platform
import re
import statistics
import subprocess
import sys
import time
from pathlib import Path

import psutil


def process_usage(master_pid):
    try:
        root = psutil.Process(master_pid)
        procs = [root, *root.children(recursive=True)]
        return {
            "rss_bytes": sum(p.memory_info().rss for p in procs if p.is_running()),
            "cpu_seconds": sum(
                p.cpu_times().user + p.cpu_times().system for p in procs if p.is_running()
            ),
        }
    except (psutil.NoSuchProcess, psutil.AccessDenied):
        return {"rss_bytes": 0, "cpu_seconds": 0}


def cgroup_cpu_seconds():
    """Use monotonically accumulated container CPU, including recycled workers."""
    output = subprocess.check_output(
        ["docker", "exec", "reqshield-fpm", "cat", "/sys/fs/cgroup/cpu.stat"],
        text=True,
    )
    counters = dict(line.split(maxsplit=1) for line in output.splitlines())
    if "usage_usec" not in counters:
        raise RuntimeError("The PHP-FPM container does not expose cgroup-v2 CPU counters.")
    return int(counters["usage_usec"]) / 1_000_000


def run_window(name, concurrency, seconds, master_pid):
    port = 8091 if name == "baseline" else 8092
    args = [
        "wrk",
        "-t", str(min(2, concurrency)),
        "-c", str(concurrency),
        "-d", f"{seconds}s",
        "--latency",
        "-s", "candidate/probes/host-http/workload.lua",
        f"http://127.0.0.1:{port}/endpoint.php",
    ]
    before = process_usage(master_pid)
    before_cpu = cgroup_cpu_seconds()
    process = subprocess.Popen(args, text=True, stdout=subprocess.PIPE, stderr=subprocess.PIPE)
    max_rss = before["rss_bytes"]
    while process.poll() is None:
        max_rss = max(max_rss, process_usage(master_pid)["rss_bytes"])
        time.sleep(0.25)
    stdout, stderr = process.communicate()
    if process.returncode != 0:
        raise RuntimeError(f"wrk failed ({name}, c={concurrency}): {stderr}\n{stdout}")
    rate = re.search(r"Requests/sec:\s*([0-9.]+)", stdout)
    percentiles = re.search(
        r"REQSHIELD_PERCENTILES_US p50=(\d+) p95=(\d+) p99=(\d+)", stdout
    )
    if not rate or not percentiles:
        raise RuntimeError(f"Unable to parse wrk throughput/latency: {stdout}")
    if re.search(r"Non-2xx or 3xx responses:\s*[1-9]", stdout):
        raise RuntimeError(f"Validation correctness failure on {name}: {stdout}")
    sockets = re.search(r"Socket errors:.*", stdout)
    if sockets and re.search(r"(connect|read|write|timeout)\s+[1-9]\d*", sockets.group()):
        raise RuntimeError(f"Transport errors on {name}: {sockets.group()}")
    after_cpu = cgroup_cpu_seconds()
    return {
        "name": name,
        "concurrency": concurrency,
        "rpm": round(float(rate.group(1)) * 60, 2),
        "p50_us": int(percentiles.group(1)),
        "p95_us": int(percentiles.group(2)),
        "p99_us": int(percentiles.group(3)),
        "max_fpm_rss_bytes": max_rss,
        "fpm_cpu_seconds": round(after_cpu - before_cpu, 3),
    }


def main():
    master_pid = int(Path("/tmp/reqshield-fpm.pid").read_text().strip())
    seconds = int(os.getenv("REQSHIELD_BENCH_WINDOW_SECONDS", "10"))
    trials = int(os.getenv("REQSHIELD_BENCH_TRIALS", "3"))
    if seconds < 8 or trials < 3:
        raise ValueError("Stable host comparison requires at least 3 trials of 8 seconds.")
    all_results = []
    for concurrency in (1, 4, 8):
        # Warm each path before sampling so opcache and autoload are loaded.
        for name in ("baseline", "candidate"):
            run_window(name, concurrency, 3, master_pid)
        for trial in range(trials):
            order = ("baseline", "candidate") if trial % 2 == 0 else ("candidate", "baseline")
            for name in order:
                result = run_window(name, concurrency, seconds, master_pid)
                result["trial"] = trial + 1
                all_results.append(result)
                print(json.dumps(result), flush=True)

    summary = []
    fail = False
    for concurrency in (1, 4, 8):
        baseline = [r["rpm"] for r in all_results if r["concurrency"] == concurrency and r["name"] == "baseline"]
        candidate = [r["rpm"] for r in all_results if r["concurrency"] == concurrency and r["name"] == "candidate"]
        base_med, cand_med = statistics.median(baseline), statistics.median(candidate)
        ratio = cand_med / base_med if base_med > 0 else 0
        delta_percent = (ratio - 1) * 100
        cv_base = statistics.pstdev(baseline) / statistics.mean(baseline)
        cv_cand = statistics.pstdev(candidate) / statistics.mean(candidate)
        stable = max(cv_base, cv_cand) <= 0.05
        accepted = stable and ratio >= 0.98
        fail |= not accepted
        summary.append({
            "concurrency": concurrency,
            "baseline_median_successful_rpm": round(base_med, 2),
            "candidate_median_successful_rpm": round(cand_med, 2),
            "delta_percent": round(delta_percent, 2),
            "baseline_cv": round(cv_base, 4),
            "candidate_cv": round(cv_cand, 4),
            "stable": stable,
            "accepted": accepted,
        })
    report = {
        "environment": platform.platform(),
        "python": sys.version,
        "time": time.strftime("%Y-%m-%dT%H:%M:%SZ", time.gmtime()),
        "baseline": "git tag 3.2",
        "candidate": os.getenv("GITHUB_SHA", "branch checkout"),
        "php": subprocess.check_output(["php", "-v"], text=True).splitlines()[0],
        "fpm": subprocess.check_output(["docker", "exec", "reqshield-fpm", "php-fpm", "-v"], text=True).splitlines()[0],
        "windows": all_results,
        "summary": summary,
    }
    Path("reqshield-host-rpm-report.json").write_text(json.dumps(report, indent=2) + "\n")
    print("HOST_RPM_SUMMARY " + json.dumps(summary), flush=True)
    if fail:
        raise RuntimeError("Matched PHP-FPM comparison breached the 2% RPM threshold or exceeded 5% sampling variance.")


if __name__ == "__main__":
    main()
