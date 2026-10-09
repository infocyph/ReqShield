wrk.method = "POST"
wrk.body = '{"seed":42}'
wrk.headers["Content-Type"] = "application/json"
wrk.headers["Connection"] = "keep-alive"

function done(summary, latency, requests)
    io.write(string.format(
        "REQSHIELD_PERCENTILES_US p50=%d p95=%d p99=%d\n",
        latency:percentile(50),
        latency:percentile(95),
        latency:percentile(99)
    ))
end
