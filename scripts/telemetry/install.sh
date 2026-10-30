#!/bin/sh
# Deliberately leaves the timer disabled. Authentication and activation are separate steps.
set -eu
umask 077
[ "$(id -u)" -eq 0 ] || { echo 'Run the installer as root.' >&2; exit 1; }
app_image=''
proxy_image=''
runner_image=''
while [ "$#" -gt 0 ]; do
    case "$1" in
        --app-image) [ "$#" -ge 2 ] || exit 2; app_image=$2; shift 2 ;;
        --runner-image) [ "$#" -ge 2 ] || exit 2; runner_image=$2; shift 2 ;;
        --proxy-image) [ "$#" -ge 2 ] || exit 2; proxy_image=$2; shift 2 ;;
        *) echo 'Expected --app-image IMAGE --proxy-image IMAGE.' >&2; exit 2 ;;
    esac
done
if [ -z "$runner_image" ]; then
printf '%s\n' "$app_image" | grep -Eq '^[a-zA-Z0-9./_-]+(:sha-[0-9a-f]{40}|:[0-9a-f]{40}|@sha256:[0-9a-f]{64})$' || { echo 'App image must use an immutable release SHA or digest.' >&2; exit 2; }
else
    printf '%s\n' "$runner_image" | grep -Eq '^ghcr\.io/murilloimparavel/caldas-gestao-telemetry-runner@sha256:[0-9a-f]{64}$' || { echo 'Runner image must use the approved GHCR repository and digest.' >&2; exit 2; }
fi
printf '%s\n' "$proxy_image" | grep -Eq '^[a-zA-Z0-9./_-]+@sha256:[0-9a-f]{64}$' || { echo 'Proxy image must use an immutable digest.' >&2; exit 2; }
source_dir=$(CDPATH='' cd -- "$(dirname -- "$0")" && pwd)
repo_dir=$(CDPATH='' cd -- "$source_dir/../.." && pwd)
install_dir=/opt/caldas-telemetry
state_dir=/var/lib/caldas-telemetry
mkdir -p "$install_dir" "$state_dir"
chmod 0755 "$install_dir"
chmod 0700 "$state_dir"
chown root:root "$install_dir" "$state_dir"
# Prevent an older timer from starting during an upgrade; preserve its output artifacts.
systemctl disable --now caldas-telemetry.timer 2>/dev/null || true
systemctl stop caldas-telemetry.service 2>/dev/null || true
systemctl stop caldas-telemetry-health.timer caldas-telemetry-health.service caldas-telemetry-watchdog.timer caldas-telemetry-watchdog.service 2>/dev/null || true
if [ -n "$runner_image" ]; then
    docker pull "$runner_image"
    docker tag "$runner_image" caldas-telemetry-runner:0.165.0
    printf '{"image":"%s"}\n' "$runner_image" > "$install_dir/runner-image.json"
    chmod 0600 "$install_dir/runner-image.json"
else
    docker image inspect "$app_image" >/dev/null
    docker build --build-arg "APP_IMAGE=$app_image" --file "$source_dir/Dockerfile.runner" --tag caldas-telemetry-runner:0.165.0 "$repo_dir"
    # A locally rebuilt image cannot silently restore an older registry version later.
    rm -f "$install_dir/runner-image.json"
fi
for file in runner.py health.py prepare.sh result.schema.json egress.conf egress.compose.yml login.sh; do
    install -o root -g root -m 0644 "$source_dir/$file" "$install_dir/$file"
done
chmod 0755 "$install_dir/login.sh"
printf 'TELEMETRY_PROXY_IMAGE=%s\n' "$proxy_image" > "$install_dir/.env"
chmod 0600 "$install_dir/.env"
docker volume create caldas-telemetry-codex-auth >/dev/null
# The only mounted volume here is the dedicated empty/auth volume. No app data is mounted.
docker run --rm --pull never --network none --user 0:0 --cap-drop ALL --cap-add CHOWN --security-opt no-new-privileges:true --read-only --memory 128m --pids-limit 32 --mount type=volume,src=caldas-telemetry-codex-auth,dst=/codex-auth caldas-telemetry-runner:0.165.0 sh -c 'chown 0:0 /codex-auth && chmod 0700 /codex-auth && chown 10001:10001 /codex-auth'
docker compose --project-name caldas-telemetry --project-directory "$install_dir" --env-file "$install_dir/.env" --file "$install_dir/egress.compose.yml" up -d
install -o root -g root -m 0644 "$source_dir/caldas-telemetry.service" /etc/systemd/system/caldas-telemetry.service
install -o root -g root -m 0644 "$source_dir/caldas-telemetry.timer" /etc/systemd/system/caldas-telemetry.timer
install -o root -g root -m 0644 "$source_dir/caldas-telemetry-watchdog.service" /etc/systemd/system/caldas-telemetry-watchdog.service
install -o root -g root -m 0644 "$source_dir/caldas-telemetry-watchdog.timer" /etc/systemd/system/caldas-telemetry-watchdog.timer
install -o root -g root -m 0644 "$source_dir/caldas-telemetry-health.service" /etc/systemd/system/caldas-telemetry-health.service
install -o root -g root -m 0644 "$source_dir/caldas-telemetry-health.timer" /etc/systemd/system/caldas-telemetry-health.timer
systemctl daemon-reload
# Cleanup remains active even when incident claims are paused or their timer is disabled.
systemctl enable --now caldas-telemetry-watchdog.timer caldas-telemetry-health.timer
printf '%s\n' 'Runner installed. The timer is disabled. Authenticate with /opt/caldas-telemetry/login.sh, then explicitly enable the timer after review.'
