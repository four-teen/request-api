#!/usr/bin/env bash
set -euo pipefail

REPO_ROOT="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd -P)"
PHP_BIN="${REQUEST_PHP_BIN:-php}"
command -v "$PHP_BIN" >/dev/null || { echo 'PHP CLI is required.' >&2; exit 1; }
command -v rsync >/dev/null || { echo 'rsync is required.' >&2; exit 1; }

# These are browser dependencies, not Composer's ignored vendor directory.
# Refuse an incomplete checkout before copying anything into the live application.
for ASSET in \
  assets/vendor/bootstrap/css/bootstrap.min.css \
  assets/vendor/bootstrap/js/bootstrap.bundle.min.js \
  assets/vendor/bootstrap-icons/bootstrap-icons.css \
  assets/vendor/bootstrap-icons/fonts/bootstrap-icons.woff2 \
  assets/vendor/boxicons/css/boxicons.min.css \
  assets/vendor/boxicons/fonts/boxicons.woff2 \
  assets/vendor/apexcharts/apexcharts.min.js; do
  if [[ ! -s "$REPO_ROOT/$ASSET" ]]; then
    echo "Missing runtime asset: $ASSET. Commit assets/vendor browser libraries before deployment." >&2
    exit 1
  fi
done

# Only the deployment path is read; database credentials are never printed or copied.
DEPLOY_PATH="$("$PHP_BIN" -r 'require $argv[1] . "/config.php"; echo getenv("REQUEST_DEPLOY_PATH") ?: "";' "$REPO_ROOT")"
if [[ -z "$DEPLOY_PATH" || "$DEPLOY_PATH" != /* || "$DEPLOY_PATH" == *$'\n'* ]]; then
  echo 'Set REQUEST_DEPLOY_PATH to an absolute application directory in the repository private .env.' >&2
  exit 1
fi
if [[ ! -d "$DEPLOY_PATH" ]]; then
  echo 'Create the application directory and its production .env before deploying.' >&2
  exit 1
fi
DEPLOY_PATH="$(cd -- "$DEPLOY_PATH" && pwd -P)"
ACCOUNT_HOME="$(cd -- "$HOME" && pwd -P)"
case "$DEPLOY_PATH" in
  "$ACCOUNT_HOME"/*) ;;
  *) echo 'Deployment must stay inside this cPanel account home directory.' >&2; exit 1 ;;
esac
if [[ "$DEPLOY_PATH" == "$ACCOUNT_HOME/public_html" || "$DEPLOY_PATH" == "$REPO_ROOT" || "$DEPLOY_PATH" == "$REPO_ROOT"/* || "$REPO_ROOT" == "$DEPLOY_PATH"/* ]]; then
  echo 'Use a dedicated application directory separate from the Git repository.' >&2
  exit 1
fi
if [[ ! -f "$DEPLOY_PATH/.env" ]]; then
  echo 'The application directory needs its own production .env before deployment.' >&2
  exit 1
fi

# The private cPanel clone can be 0700. Do not copy that directory mode to
# the public application: Apache must traverse it to read PHP and .htaccess.
# Private files remain excluded; application access rules stay in .htaccess.
# Keep deployment additive: never delete production files, uploads, or environment settings.
rsync -a --chmod=D755,F644 \
  --exclude='.git/' --exclude='.github/' --exclude='.env*' \
  --exclude='.gitignore' --exclude='.gitattributes' --exclude='.cpanel.yml' \
  --exclude='.runtime/' --exclude='profile/' --exclude='deploy/' --exclude='tests/' \
  --exclude='README.md' --exclude='serve.mjs' --exclude='router.php' \
  "$REPO_ROOT/" "$DEPLOY_PATH/"
mkdir -p -- "$DEPLOY_PATH/profile"
chmod 0755 -- "$DEPLOY_PATH/profile"
if [[ ! -f "$DEPLOY_PATH/profile/default.png" ]]; then
  cp -- "$REPO_ROOT/profile/default.png" "$DEPLOY_PATH/profile/default.png"
  chmod 0644 -- "$DEPLOY_PATH/profile/default.png"
fi
echo 'reQuest API deployment complete. Environment settings, uploads, and database were preserved.'
