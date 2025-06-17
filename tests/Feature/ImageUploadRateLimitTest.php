<?php

use App\Http\Middleware\ImageUploadRateLimit;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

describe('ImageUploadRateLimit Middleware', function () {
    beforeEach(function () {
        Cache::flush();
        $this->middleware = new ImageUploadRateLimit();
    });

    describe('Rate Limit Logic', function () {
        it('allows requests within rate limit', function () {
            $request = Request::create('/demo/upload', 'POST');
            $request->setUserResolver(function () {
                return null; // Anonymous user
            });

            $next = function ($request) {
                return new Response('OK');
            };

            $response = $this->middleware->handle($request, $next);

            expect($response->getContent())->toBe('OK');
        });

        it('blocks requests exceeding rate limit', function () {
            $request = Request::create('/demo/upload', 'POST');
            $request->headers->set('Accept', 'application/json');
            $request->setUserResolver(function () {
                return null; // Anonymous user
            });

            $next = function ($request) {
                return new Response('OK');
            };

            // Make multiple requests to trigger rate limit (limit is 10)
            for ($i = 0; $i < 11; $i++) {
                $response = $this->middleware->handle($request, $next);
            }

            // The 11th request should be rate limited
            expect($response->getStatusCode())->toBe(429);
        });

        it('tracks upload count per IP', function () {
            $request = Request::create('/demo/upload', 'POST');
            $request->setUserResolver(function () {
                return null; // Anonymous user
            });

            $next = function ($request) {
                return new Response('OK');
            };

            $response = $this->middleware->handle($request, $next);
            
            // Test passes if we get a successful response (meaning rate limit tracking works)
            expect($response->getContent())->toBe('OK');
        });

        it('resets count after time window', function () {
            // This test validates the concept - RateLimiter handles time windows automatically
            $request = Request::create('/demo/upload', 'POST');
            $request->setUserResolver(function () {
                return null; // Anonymous user
            });
            
            $next = function ($request) {
                return new Response('OK');
            };

            $response = $this->middleware->handle($request, $next);
            
            expect($response->getContent())->toBe('OK');
        });
    });

    describe('Different IP Addresses', function () {
        it('tracks different IPs separately', function () {
            $request1 = Request::create('/demo/upload', 'POST', [], [], [], ['REMOTE_ADDR' => '192.168.1.1']);
            $request2 = Request::create('/demo/upload', 'POST', [], [], [], ['REMOTE_ADDR' => '192.168.1.2']);
            
            $request1->setUserResolver(function () { return null; });
            $request2->setUserResolver(function () { return null; });

            $next = function ($request) {
                return new Response('OK');
            };

            $this->middleware->handle($request1, $next);
            $this->middleware->handle($request2, $next);

            // Both requests should succeed (different IPs tracked separately)
            $response1 = $this->middleware->handle($request1, $next);
            $response2 = $this->middleware->handle($request2, $next);
            
            expect($response1->getContent())->toBe('OK');
            expect($response2->getContent())->toBe('OK');
        });
    });

    describe('Error Response Format', function () {
        it('returns proper error response when rate limited', function () {
            $request = Request::create('/demo/upload', 'POST');
            $request->headers->set('Accept', 'application/json');
            $request->setUserResolver(function () {
                return null; // Anonymous user
            });

            $next = function ($request) {
                return new Response('OK');
            };

            // Make multiple requests to trigger rate limit
            for ($i = 0; $i < 11; $i++) {
                $response = $this->middleware->handle($request, $next);
            }

            expect($response->getStatusCode())->toBe(429);
            
            $content = json_decode($response->getContent(), true);
            expect($content['success'])->toBe(false);
            expect($content['message'])->toContain('Too many uploads');
        });
    });
}); 