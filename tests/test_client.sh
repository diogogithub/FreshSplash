#!/bin/sh
set -eu

root=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
tmp=$(mktemp -d)
trap 'rm -rf "$tmp"' EXIT HUP INT TERM
mkdir -p "$tmp/mockbin" "$tmp/home"

cat > "$tmp/mockbin/curl" <<'EOF'
#!/bin/sh
set -eu
output=
while [ "$#" -gt 0 ]; do
    case $1 in
        --output) output=$2; shift 2 ;;
        http*) printf '%s\n' "$1" > "$TEST_CURL_URL"; shift ;;
        *) shift ;;
    esac
done
[ "${TEST_CURL_FAIL:-0}" = 0 ] || exit 22
cp "$TEST_RESPONSE" "$output"
EOF

cat > "$tmp/mockbin/file" <<'EOF'
#!/bin/sh
printf '%s\n' image/jpeg
EOF

cat > "$tmp/mockbin/feh" <<'EOF'
#!/bin/sh
printf '%s\n' "$@" > "$TEST_FEH_ARGS"
EOF

cat > "$tmp/mockbin/systemctl" <<'EOF'
#!/bin/sh
exit 0
EOF
chmod +x "$tmp/mockbin/"*
PATH="$tmp/mockbin:$PATH"
export PATH
HOME="$tmp/home"
export HOME
TEST_CURL_URL="$tmp/url"
TEST_FEH_ARGS="$tmp/feh"
TEST_RESPONSE="$tmp/good"
TEST_CURL_FAIL=0
export TEST_CURL_URL TEST_FEH_ARGS TEST_RESPONSE TEST_CURL_FAIL

printf '\377\330\377\340\000\020JFIF\000\001\002\003\004\005' > "$tmp/good"
printf '<html>failure</html>\n' > "$tmp/bad"

client=$root/bin/freshsplash
output="$tmp/home/.local/share/backgrounds/wallpaper.jpg"

"$client" --server https://wallpapers.example.com --output "$output" --mode latest --set feh > /dev/null
cmp "$tmp/good" "$output"
grep -F 'mode=latest' "$TEST_CURL_URL"
grep -Fx -- '--bg-fill' "$TEST_FEH_ARGS"

TEST_RESPONSE="$tmp/bad"; export TEST_RESPONSE
if "$client" --server https://wallpapers.example.com --output "$output" >/dev/null 2>&1; then
    printf 'Invalid HTML replaced wallpaper\n' >&2
    exit 1
fi
cmp "$tmp/good" "$output"

TEST_CURL_FAIL=1; export TEST_CURL_FAIL
if "$client" --server https://wallpapers.example.com --output "$output" >/dev/null 2>&1; then
    printf 'Network failure unexpectedly succeeded\n' >&2
    exit 1
fi
cmp "$tmp/good" "$output"

if FRESHSPLASH_SERVER= "$client" --output "$output" >/dev/null 2>&1; then
    printf 'Missing server URL unexpectedly accepted\n' >&2
    exit 1
fi

"$root/bin/install-client" >/dev/null
test -x "$HOME/.local/bin/freshsplash"
test -f "$HOME/.config/freshsplash/client.env"
test -f "$HOME/.config/systemd/user/freshsplash.timer"
test -f "$HOME/.config/systemd/user/freshsplash.service"
printf '%s\n' 'FreshSplash desktop client tests passed'
