# Jaeger Tracing

Library with common classes used for Jaeger tracing.

## PHP 8

The minimum PHP version is `8.2`. To update dependencies on a system without PHP 8 use:

```shell
docker run --rm --mount type=bind,source="$(pwd)",target=/app composer:2 composer update --ignore-platform-req=ext-sockets
```
