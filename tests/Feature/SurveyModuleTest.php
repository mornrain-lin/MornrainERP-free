<?php

namespace Tests\Feature;

use App\Models\Survey;
use App\Models\SurveyOption;
use App\Models\SurveyQuestion;
use App\Models\SurveySubmission;
use App\Models\User;
use Tests\TestCase;

class SurveyModuleTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (! \App\Support\Premium::installed()) {
            $this->markTestSkipped(
                '收费私有包 mornrain/erp-premium 未安装：该模块已随「模型一」移出公开仓库，'
                .'本测试须在已安装私有包的环境运行。'
            );
        }
    }

    private static ?User $staffUser = null;

    private function admin(): User
    {
        return User::find(1);
    }

    private function staff(): User
    {
        if (self::$staffUser === null) {
            self::$staffUser = User::firstOrCreate(
                ['email' => 'staff-test@mornrain.local'],
                [
                    'name' => '测试运营',
                    'password' => User::hashPassword('staff1234'),
                    'role' => User::ROLE_STAFF,
                    'is_active' => true,
                ],
            );
        }

        return self::$staffUser;
    }

    private function createSurveyData(): array
    {
        return [
            'title' => '员工满意度调研',
            'description' => '匿名参与，感谢反馈',
            'questions' => [
                0 => ['type' => 'single', 'title' => '您对整体工作环境是否满意？', 'required' => '1', 'options' => [0 => '满意', 1 => '一般', 2 => '不满意']],
                1 => ['type' => 'score', 'title' => '请为食堂打分（1-5）', 'required' => '1', 'options' => []],
            ],
        ];
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/survey')->assertRedirect('/login');
    }

    public function test_admin_can_access_survey_index(): void
    {
        $this->actingAs($this->admin())
            ->get('/survey')
            ->assertStatus(200)
            ->assertSee('问卷');
    }

    public function test_staff_without_permission_gets_403(): void
    {
        $this->actingAs($this->staff())
            ->get('/survey')
            ->assertStatus(403);
    }

    public function test_admin_can_create_publish_and_respond_survey(): void
    {
        $admin = $this->admin();

        // 创建问卷（草稿）
        $this->actingAs($admin)
            ->post('/survey', $this->createSurveyData())
            ->assertRedirect();

        $survey = Survey::where('title', '员工满意度调研')->latest('id')->first();
        $this->assertNotNull($survey);
        $this->assertSame(Survey::STATUS_DRAFT, $survey->status);
        $this->assertCount(2, $survey->questions);
        $this->assertCount(3, $survey->questions->first()->options);

        // 详情页 200
        $this->actingAs($admin)->get("/survey/{$survey->id}")->assertStatus(200)->assertSee('题目预览');

        // 发布
        $this->actingAs($admin)
            ->post("/survey/{$survey->id}/publish")
            ->assertSessionHas('success');
        $this->assertTrue($survey->fresh()->isPublished());

        // 填写页 200
        $this->actingAs($admin)
            ->get("/survey/respond/{$survey->id}")
            ->assertStatus(200)
            ->assertSee('员工满意度调研');

        // 提交答卷
        $qSingle = SurveyQuestion::where('survey_id', $survey->id)->where('type', 'single')->first();
        $opt = SurveyOption::where('question_id', $qSingle->id)->first();
        $qScore = SurveyQuestion::where('survey_id', $survey->id)->where('type', 'score')->first();

        $this->actingAs($admin)
            ->post("/survey/respond/{$survey->id}", [
                'answers' => [
                    $qSingle->id => $opt->id,
                    $qScore->id => '4',
                ],
            ])
            ->assertSessionHas('success');

        $this->assertSame(1, SurveySubmission::where('survey_id', $survey->id)->count());

        // 重复提交被拒
        $this->actingAs($admin)
            ->post("/survey/respond/{$survey->id}", [
                'answers' => [$qSingle->id => $opt->id, $qScore->id => '5'],
            ])
            ->assertSessionHas('error');
        $this->assertSame(1, SurveySubmission::where('survey_id', $survey->id)->count());

        // 结果统计页 200
        $this->actingAs($admin)
            ->get("/survey/{$survey->id}/results")
            ->assertStatus(200)
            ->assertSee('答卷总数');
    }
}
