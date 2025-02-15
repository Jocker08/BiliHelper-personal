<?php

/**
 *  Website: https://mudew.com/
 *  Author: Lkeme
 *  License: The MIT License
 *  Email: Useri@live.cn
 *  Updated: 2021 ~ 2022
 */

namespace BiliHelper\Plugin;

use BiliHelper\Core\Log;
use BiliHelper\Tool\Generator;
use BiliHelper\Util\TimeLock;

class PolishTheMedal
{
    use TimeLock;

    private static $metal_lock = 0; // 勋章时间锁
    private static $fans_medals = []; // 全部勋章
    private static $grey_fans_medals = []; // 灰色勋章

    /**
     */
    public static function run()
    {
        if (!getEnable('polish_the_medal')) {
            return;
        }
        // 获取灰色勋章
        if (self::$metal_lock < time()) {
            // 如果勋章过多导致未处理完，就1小时一次，否则10小时一次。
            if (empty(self::$grey_fans_medals)) {
                self::fetchGreyMedalList();
                self::$metal_lock = time() + 10 * 60 * 60;
            } else {
                self::$metal_lock = time() + 1 * 60 * 60;
            }
        }
        // 点亮灰色勋章
        if (self::getLock() < time()) {
            // 随机4-10分钟处理一次点亮操作
            self::polishTheMedal();
            self::setLock(mt_rand(4, 10) * 60);
        }
    }

    /**
     * @use 点亮勋章
     */
    private static function polishTheMedal()
    {
        $medal = array_pop(self::$grey_fans_medals);
        // 为空
        if (is_null($medal)) return;
        // 特殊房间处理|央视未开播|CODE -> 11000 MSG -> ''
        if (in_array($medal['roomid'], [21686237])) return;

        Log::info("开始点亮直播间@{$medal['roomid']}的勋章");
        // 擦亮
        $response = Live::sendBarrageAPP($medal['roomid'], Generator::emoji());
        if (isset($response['code']) && $response['code'] == 0) {
            Log::notice("在直播间@{$medal['roomid']}发送点亮弹幕成功");
        } else {
            Log::warning("在直播间@{$medal['roomid']}发送点亮弹幕失败, CODE -> {$response['code']} MSG -> {$response['message']} ");
        }
    }

    /**
     * @use 获取灰色勋章列表(过滤无勋章或已满)
     */
    private static function fetchGreyMedalList()
    {
        $data = Live::fetchMedalList();
        foreach ($data as $vo) {
            // 过滤主站勋章
            if (!isset($vo['roomid'])) continue;
            // 过滤自己勋章
            if ($vo['target_id'] == getUid()) continue;
            // 所有
            self::$fans_medals[] = [
                'uid' => $vo['target_id'],
                'roomid' => $vo['roomid'],
            ];
            //  灰色
            if ($vo['medal_color_start'] == 12632256 && $vo['medal_color_end'] == 12632256 && $vo['medal_color_border'] == 12632256) {
                self::$grey_fans_medals[] = [
                    'uid' => $vo['target_id'],
                    'roomid' => $vo['roomid'],
                ];
            }
        }
        // 乱序
        shuffle(self::$grey_fans_medals);
    }
}