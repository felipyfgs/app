#!/usr/bin/env bash
# Instala bundle CA para SEFAZ/SVRS (cadeia ICP-Brasil).
set -euo pipefail

CERT_DIR="${1:-/var/www/html/storage/app/certs}"
mkdir -p "$CERT_DIR"

echo | openssl s_client -connect nfce.svrs.rs.gov.br:443 -servername nfce.svrs.rs.gov.br -showcerts 2>/dev/null \
  | awk '/BEGIN CERTIFICATE/,/END CERTIFICATE/{print}' > "$CERT_DIR/svrs-presented.pem"

ROOT_OK=0
for u in \
  "http://acraiz.icpbrasil.gov.br/credenciadas/CertificadosAC-Raiz/ICP-Brasilv10.crt" \
  "https://acraiz.icpbrasil.gov.br/credenciadas/CertificadosAC-Raiz/ICP-Brasilv10.crt" \
  "https://raw.githubusercontent.com/freeipa/freeipa-container/master/ca.crt"
do
  echo "TRY $u"
  if curl -kfsSL -m 25 -o /tmp/icp.crt "$u"; then
    if openssl x509 -in /tmp/icp.crt -inform DER -out "$CERT_DIR/ICP-Brasilv10.pem" 2>/dev/null \
      || openssl x509 -in /tmp/icp.crt -inform PEM -out "$CERT_DIR/ICP-Brasilv10.pem" 2>/dev/null; then
      ROOT_OK=1
      echo "ROOT_OK"
      break
    fi
  fi
done

# Se não baixou a raiz, ainda montamos bundle com a cadeia apresentada (pode falhar verify)
{
  cat /etc/ssl/certs/ca-certificates.crt
  echo
  cat "$CERT_DIR/svrs-presented.pem"
  if [ -f "$CERT_DIR/ICP-Brasilv10.pem" ]; then
    echo
    cat "$CERT_DIR/ICP-Brasilv10.pem"
  fi
} > "$CERT_DIR/sefaz-ca-bundle.pem"

chmod 644 "$CERT_DIR"/* || true
ls -la "$CERT_DIR"
echo "ROOT_OK=$ROOT_OK"
echo | openssl s_client -connect nfce.svrs.rs.gov.br:443 -servername nfce.svrs.rs.gov.br \
  -CAfile "$CERT_DIR/sefaz-ca-bundle.pem" 2>&1 | grep -E "Verify return code"
