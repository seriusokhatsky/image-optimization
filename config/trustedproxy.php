<?php

return [

    /*
     * Set trusted proxy IP addresses.
     *
     * Both IPv4 and IPv6 addresses are supported, along with CIDR notation.
     * The "*" character is syntactic sugar within TrustedProxy to trust any proxy
     * that connects directly to your server, a requirement when you cannot know the address
     * of your proxy (e.g. if using Rackspace, Heroku, ELB or similar).
     */
    'proxies' => '*', // Trust all proxies

    /*
     * Which headers to use to detect proxy behavior (For Symfony\Component\HttpFoundation\Request):
     *
     * Available headers: (Use integer values for best performance)
     * Illuminate\Http\Request::HEADER_X_FORWARDED_FOR    = 0b000001 (1) - X_FORWARDED_FOR
     * Illuminate\Http\Request::HEADER_X_FORWARDED_HOST   = 0b000010 (2) - X_FORWARDED_HOST
     * Illuminate\Http\Request::HEADER_X_FORWARDED_PORT   = 0b000100 (4) - X_FORWARDED_PORT
     * Illuminate\Http\Request::HEADER_X_FORWARDED_PROTO  = 0b001000 (8) - X_FORWARDED_PROTO
     * Illuminate\Http\Request::HEADER_X_FORWARDED_AWS_ELB = 0b010000 (16) - X_FORWARDED_AWS_ELB
     * Illuminate\Http\Request::HEADER_X_FORWARDED_TRAEFIK = 0b100000 (32) - X_FORWARDED_TRAEFIK
     *
     * Or, to trust all headers use:
     * Symfony\Component\HttpFoundation\Request::HEADER_X_FORWARDED_ALL (i.e., -1)
     */
    'headers' => [
        \Illuminate\Http\Request::HEADER_X_FORWARDED_FOR,
        \Illuminate\Http\Request::HEADER_X_FORWARDED_HOST,
        \Illuminate\Http\Request::HEADER_X_FORWARDED_PORT,
        \Illuminate\Http\Request::HEADER_X_FORWARDED_PROTO,
        \Illuminate\Http\Request::HEADER_X_FORWARDED_AWS_ELB,
    ],

]; 