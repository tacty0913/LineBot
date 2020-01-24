<?php

namespace App\Http\Controllers;

use App\Services\Gurunavi;
use App\Services\RestaurantBubbleBuilder;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

use LINE\LINEBot;
use LINE\LINEBot\Event\MessageEvent\TextMessage;
use LINE\LINEBot\HTTPClient\CurlHTTPClient;
use LINE\LINEBot\MessageBuilder\FlexMessageBuilder;
use LINE\LINEBot\MessageBuilder\Flex\ContainerBuilder\CarouselContainerBuilder;

class LineBotController extends Controller
{
    public function restaurants(Request $request)
    {
        Log::debug($request->header());
        Log::debug($request->input());

        // LineBotクラスのインスタンス化
        $httpClient = new CurlHTTPClient(env('LINE_ACCESS_TOKEN'));
        $lineBot = new LINEBot($httpClient, ['channelSecret' => env('LINE_CHANNEL_SECRET')]);

        $signature = $request->header('x-line-signature');
        // 署名の検証
        if (!$lineBot->validateSignature($request->getContent(), $signature)) {
            abort(400, 'Invalid signature');
        }

        $events = $lineBot->parseEventRequest($request->getContent(), $signature);

        Log::debug($events);

        foreach ($events as $event) {
            // TextMessageクラスのインスタンスであるかどうかを判定
            if (!($event instanceof TextMessage)) {
                Log::debug('Non text message has come');
                continue;
            }

            // Gurunaviクラスを生成(インスタンス化)しsearchRestaurantsメソッドにユーザーからのテキストを渡す
            $gurunavi = new Gurunavi();
            $gurunaviResponse = $gurunavi->searchRestaurants($event->getText());

            // $gurunaviResponseで、errorであるキーが存在するかを調べ、存在する場合はエラーメッセージを返信
            if (array_key_exists('error', $gurunaviResponse)) {
                
                $replyText = $gurunaviResponse['error'][0]['message'];
                // getReplyTokenメソッドで、応答トークンを取得
                $replyToken = $event->getReplyToken();
                $lineBot->replyText($replyToken, $replyText);
                continue;
            }
           
            // 初期化
            $bubbles = [];
            // 飲食店検索結果の情報を1個ずつ取り出し、繰り返し処理
            foreach ($gurunaviResponse['rest'] as $restaurant) {
                // 空のインスタンスを生成
                $bubble = RestaurantBubbleBuilder::builder();
                // 飲食店検索結果の情報をRestaurantBubbleBuilderインスタンスが持つ各種プロパティに代入
                $bubble->setContents($restaurant);
                // 配列$bubblesの最後に、RestaurantBubbleBuilderインスタンスを追加
                $bubbles[] = $bubble;
            }

            // 空のインスタンスを生成
            $carousel = CarouselContainerBuilder::builder();
            // CarouselContainerBuilderインスタンスのプロパティcontentsに、$bubblesを代入
            $carousel->setContents($bubbles);

            // 空のインスタンスを生成
            $flex = FlexMessageBuilder::builder();
            $flex->setAltText('飲食店検索結果');
            // FlexMessageBuilderインスタンスのプロパティcontentsに、$carouselを代入
            $flex->setContents($carousel);

            $lineBot->replyMessage($event->getReplyToken(), $flex);
        }
    }
}