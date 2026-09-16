# signal-cli needs a JRE far newer than Debian (php:apache's base) ships,
# so borrow the JRE from eclipse-temurin instead of apt-installing one.
FROM eclipse-temurin:25-jre AS java

FROM php:apache

ENV SIGNAL_CLI_VERSION=0.14.8
# libsignal-client version bundled in signal-cli's lib/libsignal-client-<version>.jar —
# check that filename when bumping SIGNAL_CLI_VERSION, and update this to match.
ENV LIBSIGNAL_VERSION=0.102.1
ENV JAVA_HOME=/opt/java/openjdk
ENV PATH="${JAVA_HOME}/bin:${PATH}"
# Without a UTF-8 locale, Java decodes exec()'d command-line arguments (sun.jnu.encoding)
# as ASCII, replacing every non-ASCII byte with U+FFFD — mangling color/printer names
# with accents or dashes before signal-cli ever sees them. C.UTF-8 needs no locale-gen.
ENV LANG=C.UTF-8
ENV LC_ALL=C.UTF-8

COPY --from=java /opt/java/openjdk /opt/java/openjdk

RUN apt-get update && \
    apt-get install -y --no-install-recommends wget ca-certificates && \
    wget -q "https://github.com/AsamK/signal-cli/releases/download/v${SIGNAL_CLI_VERSION}/signal-cli-${SIGNAL_CLI_VERSION}.tar.gz" && \
    tar xf "signal-cli-${SIGNAL_CLI_VERSION}.tar.gz" -C /opt && \
    rm "signal-cli-${SIGNAL_CLI_VERSION}.tar.gz" && \
    ln -sf "/opt/signal-cli-${SIGNAL_CLI_VERSION}/bin/signal-cli" /usr/local/bin/ && \
    ARCH=$(dpkg --print-architecture) && \
    if [ "$ARCH" != "amd64" ]; then \
        case "$ARCH" in \
            arm64) RUST_TARGET=aarch64-unknown-linux-gnu ;; \
            armhf) RUST_TARGET=armv7-unknown-linux-gnueabihf ;; \
            *) echo "No known libsignal native lib for architecture: $ARCH" >&2 && exit 1 ;; \
        esac && \
        wget -q "https://github.com/exquo/signal-libs-build/releases/download/libsignal_v${LIBSIGNAL_VERSION}/libsignal_jni.so-v${LIBSIGNAL_VERSION}-${RUST_TARGET}.tar.gz" && \
        tar xzf "libsignal_jni.so-v${LIBSIGNAL_VERSION}-${RUST_TARGET}.tar.gz" -C /usr/lib && \
        rm "libsignal_jni.so-v${LIBSIGNAL_VERSION}-${RUST_TARGET}.tar.gz"; \
    fi && \
    rm -rf /var/lib/apt/lists/*
