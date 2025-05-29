## Image optimizer API

This Laravel app is built to provide an API for image optimization. It is built inside docker with extra software installed for image optimizations.


### What is customized in original docker sail image?

Add a few commands to the `docker/8.4/Dockerfile`

```
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libwebp-dev \
    libzip-dev \
    # Image optimization binaries
    jpegoptim \
    optipng \
    pngquant \
    gifsicle \
    # WebP tools
    webp \
    # AVIF tools
    libavif-dev \
    libavif-bin \
    # Build dependencies for MozJPEG
    automake \
    autoconf \
    libtool \
    cmake \
    nasm \
    build-essential

# Install MozJPEG
RUN cd /tmp && \
    git clone https://github.com/mozilla/mozjpeg.git && \
    cd mozjpeg && \
    mkdir build && \
    cd build && \
    cmake -DCMAKE_INSTALL_PREFIX=/usr/local .. && \
    make && \
    make install && \
    ldconfig && \
    rm -rf /tmp/mozjpeg
```