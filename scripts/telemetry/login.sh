#!/bin/sh
# API credentials are read only from stdin; never from command-line arguments or shell env.
set -eu
[ "$(id -u)" -eq 0 ] || { echo 'Run authentication as root.' >&2; exit 1; }
method=${1:-}
case "$method" in
    device) set -- login --device-auth ;;
    api) set -- login --with-api-key ;;
    *) echo 'Usage: login.sh device | login.sh api (API key via stdin only).' >&2; exit 2 ;;
esac
# API-key stdin must not be attached to a pseudo-terminal (avoid echoing credentials).
if [ "$method" = api ] && [ -t 0 ]; then
    echo 'Pass the API key through stdin from a protected secret reader, never as an argument.' >&2
    exit 2
fi
if [ "$method" = device ]; then
    # The device flow displays a short-lived code for the human to approve in a browser.
    exec docker run --rm -it --network caldas-telemetry-internal --user 10001:10001 --cap-drop ALL --security-opt no-new-privileges:true --read-only --tmpfs /tmp:rw,nosuid,nodev,size=64m,mode=1777 --cpus 1 --memory 512m --pids-limit 64 --env HOME=/tmp/telemetry-home --env CODEX_HOME=/codex-auth --env HTTPS_PROXY=http://telemetry-egress:3128 --env HTTP_PROXY=http://telemetry-egress:3128 --mount type=volume,src=caldas-telemetry-codex-auth,dst=/codex-auth caldas-telemetry-runner:0.165.0 codex "$@"
fi
exec docker run --rm -i --network caldas-telemetry-internal --user 10001:10001 --cap-drop ALL --security-opt no-new-privileges:true --read-only --tmpfs /tmp:rw,nosuid,nodev,size=64m,mode=1777 --cpus 1 --memory 512m --pids-limit 64 --env HOME=/tmp/telemetry-home --env CODEX_HOME=/codex-auth --env HTTPS_PROXY=http://telemetry-egress:3128 --env HTTP_PROXY=http://telemetry-egress:3128 --mount type=volume,src=caldas-telemetry-codex-auth,dst=/codex-auth caldas-telemetry-runner:0.165.0 codex "$@"
