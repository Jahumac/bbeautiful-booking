FROM alextselegidis/easyappointments:latest

# ---------------------------------------------------------------------------
# Bbeautiful customised build
# Overlays the Bbeautiful booking app customisations (stacked booking,
# Reports, Invoicing, VAT, branding) onto the official Easy!Appointments base.
# Run as your OWN image (bbeautiful-booking) so it no longer presents as the
# upstream author's container in Unraid.
# ---------------------------------------------------------------------------

COPY application/ /var/www/html/application/
COPY assets/js/pages/ /var/www/html/assets/js/pages/
COPY assets/js/utils/ /var/www/html/assets/js/utils/
COPY assets/js/components/ /var/www/html/assets/js/components/
COPY assets/img/logo.png assets/img/logo-16x16.png assets/img/favicon.ico /var/www/html/assets/img/

# Identify the image as yours.
ARG VERSION=latest
LABEL org.opencontainers.image.title="Bbeautiful"
LABEL org.opencontainers.image.description="Bbeautiful home beauty & nail salon booking app (Easy!Appointments fork)"
LABEL org.opencontainers.image.source="https://github.com/Jahumac/bbeautiful-booking"
LABEL org.opencontainers.image.licenses="GPL-3.0"
LABEL org.opencontainers.image.version="$VERSION"

# Unraid dashboard metadata — round Bbeautiful logo as the container icon and
# register as a Compose Manager "Compose Stack" so it is editable from the UI.
LABEL net.unraid.docker.managed="composeman"
LABEL net.unraid.docker.icon="http://10.1.1.4:8086/assets/img/logo.png"
LABEL net.unraid.docker.webui="http://10.1.1.4:8086"
LABEL net.unraid.docker.template=""

# The upstream entrypoint regenerates config.php from env vars at boot (BASE_URL
# / DB_* / MAIL_* env). Provide them in the compose file.

# ---------------------------------------------------------------------------
# NOTE on build assets:
# The repo does NOT ship compiled assets/vendor + assets/css (build artifacts).
# This image is layered on the official image which provides those, and only
# overlays the customised application/ + JS + logo. It is a "Bbeautiful" image
# whose underlying base is the upstream one — that is the pragmatic, low-risk
# choice and avoids committing gigabytes of build output. The running image is
# tagged/owned by Bbeautiful regardless.
# ---------------------------------------------------------------------------
