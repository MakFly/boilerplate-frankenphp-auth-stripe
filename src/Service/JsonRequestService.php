<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\HttpFoundation\Request;

class JsonRequestService
{
    /**
     * @return array{email: string, password: string}
     */
    public static function getJsonRequest(Request $request): array
    {
        return json_decode($request->getContent(), true);
    }

    /**
     * @return array{email: string, password: string}
     */
    public function getContent(Request $request): array
    {
        return json_decode($request->getContent(), true);
    }
}