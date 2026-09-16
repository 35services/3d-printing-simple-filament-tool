FROM eclipse-temurin:21-jre

ENV VERSION=0.14.8

RUN apt-get update && \
    apt-get install -y wget tar && \
    wget https://github.com/AsamK/signal-cli/releases/download/v${VERSION}/signal-cli-${VERSION}.tar.gz && \
    tar xf signal-cli-${VERSION}.tar.gz -C /opt && \
    rm signal-cli-${VERSION}.tar.gz && \
    ln -sf /opt/signal-cli-${VERSION}/bin/signal-cli /usr/local/bin/

ENTRYPOINT ["signal-cli"]
