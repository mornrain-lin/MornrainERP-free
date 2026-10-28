<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Route;

class RoadmapController extends Controller
{
    /** 产品路线图 / 模块总览：全模块地图 + 上线进度 */
    public function index()
    {
        $groups = config('modules', []);

        $ready = 0;
        $planned = 0;
        foreach ($groups as $group) {
            foreach ($group['items'] ?? [] as $it) {
                if (($it['status'] ?? 'planned') === 'ready') {
                    $ready++;
                } else {
                    $planned++;
                }
            }
        }

        return view('roadmap', compact('groups', 'ready', 'planned'));
    }
}
