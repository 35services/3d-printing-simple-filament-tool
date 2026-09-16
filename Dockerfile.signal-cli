FROM eclipse-temurin:25-jre

ENV VERSION=0.14.8
# libsignal-client version bundled in signal-cli's lib/libsignal-client-<version>.jar —
# check that filename when bumping VERSION, and update this to match.
ENV LIBSIGNAL_VERSION=0.102.1

RUN apt-get update && \
    apt-get install -y wget tar && \
    wget https://github.com/AsamK/signal-cli/releases/download/v${VERSION}/signal-cli-${VERSION}.tar.gz && \
    tar xf signal-cli-${VERSION}.tar.gz -C /opt && \
    rm signal-cli-${VERSION}.tar.gz && \
    ln -sf /opt/signal-cli-${VERSION}/bin/signal-cli /usr/local/bin/ && \
    ARCH=$(dpkg --print-architecture) && \
    if [ "$ARCH" != "amd64" ]; then \
        case "$ARCH" in \
            arm64) RUST_TARGET=aarch64-unknown-linux-gnu ;; \
            armhf) RUST_TARGET=armv7-unknown-linux-gnueabihf ;; \
            *) echo "No known libsignal native lib for architecture: $ARCH" >&2 && exit 1 ;; \
        esac && \
        wget "https://github.com/exquo/signal-libs-build/releases/download/libsignal_v${LIBSIGNAL_VERSION}/libsignal_jni.so-v${LIBSIGNAL_VERSION}-${RUST_TARGET}.tar.gz" && \
        tar xzf "libsignal_jni.so-v${LIBSIGNAL_VERSION}-${RUST_TARGET}.tar.gz" -C /usr/lib && \
        rm "libsignal_jni.so-v${LIBSIGNAL_VERSION}-${RUST_TARGET}.tar.gz"; \
    fi

ENTRYPOINT ["signal-cli"]
