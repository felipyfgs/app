# syntax=docker/dockerfile:1

FROM golang:1.25-alpine AS builder

RUN apk add --no-cache ca-certificates git

WORKDIR /src/apps/whatsapp-gateway

COPY apps/whatsapp-gateway/go.mod apps/whatsapp-gateway/go.sum ./
RUN go mod download

COPY apps/whatsapp-gateway/ ./

ARG TARGETOS=linux
ARG TARGETARCH
RUN CGO_ENABLED=0 GOOS=${TARGETOS} GOARCH=${TARGETARCH} \
    go build -trimpath -ldflags="-s -w" -o /out/whatsapp-gateway ./cmd/gateway

FROM alpine:3.22 AS runtime

RUN apk add --no-cache ca-certificates tzdata \
    && addgroup -S -g 10001 gateway \
    && adduser -S -D -H -u 10001 -G gateway gateway \
    && install -d -o gateway -g gateway -m 0700 /var/lib/whatsapp-gateway/spool

ARG RELEASE_SHA=unknown
ARG BUILD_DATE=

LABEL org.opencontainers.image.revision="${RELEASE_SHA}" \
      org.opencontainers.image.created="${BUILD_DATE}" \
      org.opencontainers.image.source="https://github.com/local/fiscal-hub" \
      org.opencontainers.image.title="fiscal-hub-whatsapp-gateway"

COPY --from=builder --chown=gateway:gateway /out/whatsapp-gateway /usr/local/bin/whatsapp-gateway

ENV WHATSAPP_GATEWAY_HTTP_ADDRESS=:8080 \
    WHATSAPP_GATEWAY_SPOOL_DIR=/var/lib/whatsapp-gateway/spool

USER gateway:gateway
EXPOSE 8080

HEALTHCHECK --interval=15s --timeout=5s --start-period=10s --retries=5 \
    CMD wget -qO- http://127.0.0.1:8080/healthz >/dev/null || exit 1

ENTRYPOINT ["/usr/local/bin/whatsapp-gateway"]
