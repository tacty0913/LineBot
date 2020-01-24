<?php

namespace App\Services;

use GuzzleHttp\Client;

class Gurunavi
{
    // ぐるなびのAPIを定数で定義
    private const RESTAURANTS_SEARCH_API_URL = 'https://api.gnavi.co.jp/RestSearchAPI/v3/';

    public function searchRestaurants(string $word): array
    {
        // Clientクラスを生成
        $client = new Client();

        // 第一引数には、リクエスト先のURL
        // 第二引数には、オプションとなる情報を連想配列で指定
        $response = $client
            ->get(self::RESTAURANTS_SEARCH_API_URL, [
                'query' => [
                    'keyid' => env('GURUNAVI_ACCESS_KEY'),
                    'freeword' => $word,
                ],
                // 例外を発生させないための処理
                'http_errors' => false,
            ]);
        // レスポンスボディを返す
        return json_decode($response->getBody()->getContents(), true);
    }
}