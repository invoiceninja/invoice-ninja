<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace Tests\Feature;

use App\DataMapper\ClientSettings;
use App\Models\Client;
use App\Models\Project;
use App\Models\Task;
use App\Utils\Traits\MakesHash;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\ValidationException;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * 
 *  App\Http\Controllers\TaskController
 */
class TaskApiTest extends TestCase
{
    use MakesHash;
    use DatabaseTransactions;
    use MockAccountData;

    private $faker;

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();

        Session::start();

        $this->faker = \Faker\Factory::create();

        Model::reguard();
    }

    private function checkTimeLog(array $log): bool
    {
        if (count($log) == 0) {
            return true;
        }

        /*Get first value of all arrays*/
        $result = array_column($log, 0);

        /*Sort the array in ascending order*/
        asort($result);

        $new_array = [];

        /*Rebuild the array in order*/
        foreach ($result as $key => $value) {
            $new_array[] = $log[$key];
        }

        /*Iterate through the array and perform checks*/
        foreach ($new_array as $key => $array) {
            /*Flag which helps us know if there is a NEXT timelog*/
            $next = false;
            /* If there are more than 1 time log in the array, ensure the last timestamp is not zero*/
            if (count($new_array) > 1 && $array[1] == 0) {
                return false;
            }

            /* Check if the start time is greater than the end time */
            /* Ignore the last value for now, we'll do a separate check for this */
            if ($array[0] > $array[1] && $array[1] != 0) {
                return false;
            }

            /* Find the next time log value - if it exists */
            if (array_key_exists($key + 1, $new_array)) {
                $next = $new_array[$key + 1];
            }

            /* check the next time log and ensure the start time is GREATER than the end time of the previous record */
            if ($next && $next[0] < $array[1]) {
                return false;
            }

            /* Get the last row of the timelog*/
            $last_row = end($new_array);

            /*If the last value is NOT zero, ensure start time is not GREATER than the endtime */
            if ($last_row[1] != 0 && $last_row[0] > $last_row[1]) {
                return false;
            }

            return true;
        }
    }

    public function testTaskLogGenerationforInvoices()
    {
        $this->company->invoice_task_datelog = true;
        $this->company->invoice_task_timelog = true;
        $this->company->invoice_task_hours = true;
        $this->company->invoice_task_item_description = true;
        $this->company->invoice_task_project = true;

        $settings = $this->company->settings;
        $settings->default_task_rate = 100;
        $settings->allow_billable_task_items = true;
        $settings->show_task_item_description = true;
        $this->company->settings = $settings;
        $this->company->save();

        $p = Project::factory()->create([
            'client_id' => $this->client->id,
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'name' => 'Fancy Project Inc',
        ]);

        $data = [
            'project_id' => $p->hashed_id,
            'client_id' =>$this->client->hashed_id,
            'time_log' => '[[1731391977,1731399177,"item description",true],[1731399178,1731499177,"item description 2", true]]',
            'description' => 'Top level Task Description',
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/tasks', $data);

        $response->assertStatus(200);

        $arr = $response->json();
        $id = $arr['data']['id'];

        $task = Task::find($this->decodePrimaryKey($id));

        $this->assertNotNull($task);
        // nlog($task->time_log);
        // nlog($task->description());
    }

    public function testBadRequestCaseOne()
    {
        
        $data = [
            'client_id' => $this->client->hashed_id,
            'description' => 'Demo from client',
            'duration_hours' => '2700000',
            'time_details' => [
                [
                    'duration_hours' => 2700000
                ]
            ]
        ];

        $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token,
            ])->postJson("/api/v1/tasks", $data);

        $response->assertStatus(200);

    }

    public function testCaseOne()
    {
        $time_log = '[[1729552249,1729553149],[1729553415,1729554315],[1729555129,1729556929],[1729557879,1729565079],[1729565184,1729568784]]';

            $c = Client::factory()->create([
                'user_id' => $this->user->id,
                'company_id' => $this->company->id,
            ]);

            $data = [
                'client_id' => $c->hashed_id,
                'description' => 'Test Task',
                'time_log' => $time_log,
            ];

            $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token,
            ])->postJson("/api/v1/tasks", $data);


            $response->assertStatus(200);

            
            $response = $this->withHeaders([
               'X-API-SECRET' => config('ninja.api_secret'),
               'X-API-TOKEN' => $this->token,
           ])->postJson("/api/v1/tasks?start=true", $data);


            $response->assertStatus(200);


            $response = $this->withHeaders([
               'X-API-SECRET' => config('ninja.api_secret'),
               'X-API-TOKEN' => $this->token,
           ])->postJson("/api/v1/tasks?stop=true", $data);


            $response->assertStatus(200);


    }

    public function testTimeLogWithSameStartAndStopTimes()
    {
        $settings = ClientSettings::defaults();
        $settings->default_task_rate = 41;

        $c = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'settings' => $settings,
        ]);

        $data = [
            'client_id' => $c->hashed_id,
            'description' => 'Test Task',
            'time_log' => '[[1681165417,1681165432,"sumtin",true],[1681165446,1681165446]]',
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson("/api/v1/tasks", $data);

        $response->assertStatus(200);
        $arr = $response->json();

    }

    public function testRoundingViaApi()
    {

        $data = [
            'client_id' => $this->client->hashed_id,
            'description' => 'Test Task',
            'time_log' => '[[1681165417,1681165432,"sumtin",true],[1681165446,0]]',
            'assigned_user' => [],
            'project' => [],
            'user' => [],
            // 'status' => [],
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson("/api/v1/tasks", $data);

        $response->assertStatus(200);

    }

    public function testRoundingToNearestXXX()
    {

        $time = 1680036807;

        $round_up_to_next_minute = ceil($time / 60) * 60;
        $round_down_to_next_minute = floor($time / 60) * 60;
        $this->assertEquals(1680036840, $round_up_to_next_minute);
        $this->assertEquals(1680036780, $round_down_to_next_minute);

        $round_up_to_next_minute = ceil($round_up_to_next_minute / 3600) * 3600;
        $round_down_to_next_minute = floor($round_down_to_next_minute / 3600) * 3600;
        $this->assertEquals(1680037200, $round_up_to_next_minute);
        $this->assertEquals(1680033600, $round_down_to_next_minute);

    }

    public function testKsortPerformance()
    {
        $logs = [
        [1680035007,1680036807,"",true],
        [1681156840,1681158000,"",true],
        [1680302433,1680387960,"",true],
        [1680715620,1680722820,"",true],
        [1,1680737460,"",true]
        ];

        $key_values = array_column($logs, 0);
        array_multisort($key_values, SORT_ASC, $logs);

        $start = $logs[0];

        $this->assertEquals(1, $start[0]);

    }

    public function testTaskDivisionByZero()
    {
        $data = [
        "rate" => 0,
        "time_log" => '[[1719350900,1719352700,"",true]]',
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson("/api/v1/tasks", $data);

        $response->assertStatus(200);

    }

    public function testRequestRuleParsing()
    {

        $data = [
            'client_id' => $this->client->hashed_id,
            'description' => 'Test Task',
            'time_log' => '[[1681165417,1681165432,"sumtin",true],[1681165446,0]]',
            'assigned_user' => [],
            'project' => [],
            'user' => [],
            // 'status' => [],
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson("/api/v1/tasks", $data);

        $response->assertStatus(200);
        $arr =  $response->json();

        $data = [
            'client_id' => $this->client->hashed_id,
            'description' => 'Test Task',
            'time_log' => '[""]',
            'assigned_user' => [],
            'project' => [],
            'user' => [],
        ];


        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->putJson("/api/v1/tasks/".$arr['data']['id'], $data);

        $response->assertStatus(200);


    }
    public function testUserFilters()
    {

        $response = $this->withHeaders([
                    'X-API-SECRET' => config('ninja.api_secret'),
                    'X-API-TOKEN' => $this->token,
                ])->getJson("/api/v1/tasks")->assertStatus(200);

        $response = $this->withHeaders([
                    'X-API-SECRET' => config('ninja.api_secret'),
                    'X-API-TOKEN' => $this->token,
                ])->getJson("/api/v1/tasks?user_id={$this->user->hashed_id}");

        $response->assertStatus(200);

        $arr = $response->json();

        $this->assertEquals($this->user->hashed_id, $arr['data'][0]['user_id']);
        $this->assertCount(1, $arr['data']);

        $t = Task::factory()->create([
            'client_id' => $this->client->id,
            'user_id' => $this->user->id,
            'assigned_user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'description' => 'Test Task',
            'time_log' => '[[1681165417,1681165432,"sumtin",true],[1681165446,0]]',
        ]);

        $response = $this->withHeaders([
                    'X-API-SECRET' => config('ninja.api_secret'),
                    'X-API-TOKEN' => $this->token,
                ])->getJson("/api/v1/tasks?assigned_user={$this->user->hashed_id}");

        $response->assertStatus(200);

        $arr = $response->json();

        $this->assertEquals($this->user->hashed_id, $arr['data'][0]['user_id']);
        $this->assertEquals($this->user->hashed_id, $arr['data'][0]['assigned_user_id']);
        $this->assertCount(1, $arr['data']);

    }

    public function testEmptyTimeLogArray()
    {

        $data = [
            'client_id' => $this->client->id,
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'description' => 'Test Task',
            'time_log' => null,
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson("/api/v1/tasks", $data);

        $response->assertStatus(200);

        $data = [
            'client_id' => $this->client->id,
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'description' => 'Test Task',
            'time_log' => '',
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson("/api/v1/tasks", $data);

        $response->assertStatus(200);

        $data = [
           'client_id' => $this->client->id,
           'user_id' => $this->user->id,
           'company_id' => $this->company->id,
           'description' => 'Test Task',
           'time_log' => '[]',
       ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson("/api/v1/tasks", $data);

        $response->assertStatus(200);

        $data = [
            'client_id' => $this->client->id,
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'description' => 'Test Task',
            'time_log' => '{}',
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson("/api/v1/tasks", $data);

        $response->assertStatus(200);
    }

    public function testFaultyTimeLogArray()
    {

        $data = [
            'client_id' => $this->client->id,
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'description' => 'Test Task',
            'time_log' => 'ABBA is the best band in the world',
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson("/api/v1/tasks", $data);

        $response->assertStatus(422);

    }

    public function testTaskClientRateSet()
    {
        $settings = ClientSettings::defaults();
        $settings->default_task_rate = 41;

        $c = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'settings' => $settings,
        ]);

        $data = [
            'client_id' => $c->hashed_id,
            'description' => 'Test Task',
            'time_log' => '[[1681165417,1681165432,"sumtin",true],[1681165446,0]]',
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson("/api/v1/tasks", $data);

        $response->assertStatus(200);
        $arr = $response->json();

        $this->assertEquals(41, $arr['data']['rate']);
    }

    public function testTaskTimelogParse()
    {
        $data = [
            "description" => "xx",
            "rate" => "6574",
            "time_log" => "[[Oct 31, 2023 12:00 am,Oct 31, 2023 1:00 am]]"
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson("/api/v1/tasks", $data);

        $response->assertStatus(422);
        $arr = $response->json();



    }

    public function testTaskProjectRateSet()
    {

        $p = Project::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'name' => 'proggy',
            'task_rate' => 101,
        ]);

        $data = [
            'project_id' => $p->hashed_id,
            'client_id' => $this->client->id,
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'description' => 'Test Task',
            'time_log' => '[[1681165417,1681165432,"sumtin",true],[1681165446,0]]',
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson("/api/v1/tasks", $data);

        $response->assertStatus(200);
        $arr = $response->json();

        $this->assertEquals(101, $arr['data']['rate']);
    }

    public function testStatusSet()
    {

        $data = [
            'client_id' => $this->client->id,
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'description' => 'Test Task',
            'time_log' => '[[1681165417,1681165432,"sumtin",true],[1681165446,0]]',
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson("/api/v1/tasks");

        $response->assertStatus(200);
        $arr = $response->json();

        $this->assertNotEmpty($arr['data']['status_id']);
    }

    public function testStartDate()
    {
        $x = [];

        $this->assertFalse(isset($x[0][0]));

        $x[0][0] = 'a';

        $this->assertTrue(isset($x[0][0]));

        $this->assertNotNull(\Carbon\Carbon::createFromTimestamp($x[0][0]));

    }

    public function testMultiSortArray()
    {

        $logs = [
            [1680035007,1680036807,"",true],
        ];

        $key_values = array_column($logs, 0);
        array_multisort($key_values, SORT_ASC, $logs);

        $start = $logs[0];

        $this->assertEquals(1680035007, $start[0]);

        $logs = [
        ];

        $key_values = array_column($logs, 0);
        array_multisort($key_values, SORT_ASC, $logs);

        $this->assertIsArray($logs);



    }
    public function testStartStopSanity()
    {

        $task = Task::factory()->create([
            'client_id' => $this->client->id,
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'description' => 'Test Task',
            'time_log' => '[[1681165417,1681165432,"sumtin",true],[1681165446,0]]',
        ]);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->putJson("/api/v1/tasks/{$task->hashed_id}?stop=true");

        $response->assertStatus(200);

        $task->time_log = 'A very strange place';

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->putJson("/api/v1/tasks/{$task->hashed_id}?stop=true", $task->toArray());

        $response->assertStatus(422);

        $task->time_log = null;

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->putJson("/api/v1/tasks/{$task->hashed_id}?stop=true", $task->toArray());

        $response->assertStatus(200);

        $task->time_log = '';

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->putJson("/api/v1/tasks/{$task->hashed_id}?stop=true", $task->toArray());

        $response->assertStatus(200);


        $task->time_log = '{}';

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->putJson("/api/v1/tasks/{$task->hashed_id}?stop=true", $task->toArray());

        $response->assertStatus(200);



    }

    public function testStoppingTaskWithDescription()
    {
        $task = Task::factory()->create([
            'client_id' => $this->client->id,
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'description' => 'Test Task',
            'time_log' => '[[1681165417,1681165432,"sumtin",true],[1681165446,0]]',
        ]);

        $task_repo = new \App\Repositories\TaskRepository();

        $task = $task_repo->stop($task);

        $log = json_decode($task->time_log);

        $last = end($log);

        $this->assertNotEquals(0, $last[1]);
        $this->assertCount(2, $last);
    }

    public function testMultiDimensionArrayOfTimes()
    {
        $logs = [
        '[[1680302433,1680387960,"",true]]',
        '[[1680715620,1680722820,"",true],[1680729660,1680737460,"",true]]',
        '[[1681156840,1681158000,"",true]]',
        '[[1680035007,1680036807,"",true]]',
        ];

        foreach($logs as $log) {
            $this->assertTrue($this->checkTimeLog(json_decode($log)));
        }

    }

    public function testArrayOfTimes()
    {
        $logs = [
        "[[1675275148,1675277829]]",
        "[[1675375200,1675384200],[1676074247,1676074266]]",
        "[[1675443600,1675461600],[1676053305,1676055950],[1676063112,1676067834]]",
        "[[1676068200,1676070900]]",
        "[[1678134638,1678156238]]",
        "[[1678132800,1678134582],[1678134727,1678136801]]",
        "[[1678343569,1678344469]]",
        "[[1678744339,1678755139]]",
        "[[1678894860,1678906620]]",
        "[[1679339870,1679341672]]",
        "[[1680547478,1680547482]]",
        "[[1681156881,0]]",
        ];

        foreach($logs as $log) {
            $this->assertTrue($this->checkTimeLog(json_decode($log)));
        }

    }


    public function testTimeLogChecker1()
    {
        $log = [
            [50,0]
        ];

        $this->assertTrue($this->checkTimeLog($log));
    }

    public function testTimeLogChecker2()
    {
        $log = [
            [4,5],
            [5,1]
        ];


        $this->assertFalse($this->checkTimeLog($log));
    }


    public function testTimeLogChecker3()
    {
        $log = [
            [4,5],
            [3,50]
        ];


        $this->assertFalse($this->checkTimeLog($log));
    }


    public function testTimeLogChecker4()
    {
        $log = [
            [4,5],
            [3,0]
        ];


        $this->assertFalse($this->checkTimeLog($log));
    }

    public function testTimeLogChecker5()
    {
        $log = [
            [4,5],
            [3,1]
        ];


        $this->assertFalse($this->checkTimeLog($log));
    }

    public function testTimeLogChecker6()
    {
        $log = [
            [4,5],
            [1,3],
        ];


        $this->assertTrue($this->checkTimeLog($log));
    }

    public function testTimeLogChecker7()
    {
        $log = [
            [1,3],
            [4,5]
        ];


        $this->assertTrue($this->checkTimeLog($log));
    }

    public function testTimeLogChecker8()
    {
        $log = [
            [1,3],
            [50,0]
        ];

        $this->assertTrue($this->checkTimeLog($log));
    }

    public function testTimeLogChecker9()
    {
        $log = [
            [4,5,'bb'],
            [50,0,'aa'],
        ];

        $this->assertTrue($this->checkTimeLog($log));
    }



    public function testTimeLogChecker10()
    {
        $log = [
            [4,5,'5'],
            [50,0,'3'],
        ];

        $this->assertTrue($this->checkTimeLog($log));
    }


    public function testTimeLogChecker11()
    {
        $log = [
            [1,2,'a'],
            [3,4,'d'],
        ];

        $this->assertTrue($this->checkTimeLog($log));
    }


    public function testTimeLogChecker12()
    {
        $log = [
            [1,2,'a',true],
            [3,4,'d',false],
        ];

        $this->assertTrue($this->checkTimeLog($log));
    }

    public function testTaskListWithProjects()
    {

        $project = Project::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'name' => 'proggy',
        ]);

        $data = [
            'project_id' => $this->encodePrimaryKey($project->id),
            'timelog' => [[1,2,'a'],[3,4,'d']],
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/tasks?include=project', $data);

        $response->assertStatus(200);

        $arr = $response->json();

        $this->assertEquals('proggy', $arr['data']['project']['name']);

    }

    public function testTaskListClientStatus()
    {
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/tasks?client_status=invoiced')
          ->assertStatus(200);
    }

    public function testTaskLockingGate()
    {
        $data = [
            'timelog' => [[1,2,'a'],[3,4,'d']],
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/tasks', $data);

        $arr = $response->json();
        $response->assertStatus(200);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->putJson('/api/v1/tasks/' . $arr['data']['id'], $data);

        $arr = $response->json();

        $response->assertStatus(200);

        $task = Task::find($this->decodePrimaryKey($arr['data']['id']));
        $task->invoice_id = $this->invoice->id;
        $task->save();

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->putJson('/api/v1/tasks/' . $arr['data']['id'], $data);

        $arr = $response->json();

        $response->assertStatus(200);

        $task = Task::find($this->decodePrimaryKey($arr['data']['id']));
        $task->company->invoice_task_lock = true;
        $task->invoice_id = $this->invoice->id;
        $task->push();

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->putJson('/api/v1/tasks/' . $arr['data']['id'], $data);

        $arr = $response->json();

        $response->assertStatus(401);
    }


    // public function testTaskLocking()
    // {
    //     $data = [
    //         'timelog' => [[1,2],[3,4]],
    //     ];

    //     $response = $this->withHeaders([
    //         'X-API-SECRET' => config('ninja.api_secret'),
    //         'X-API-TOKEN' => $this->token,
    //     ])->post('/api/v1/tasks', $data);

    //     $arr = $response->json();
    //     $response->assertStatus(200);


    //     $response = $this->withHeaders([
    //         'X-API-SECRET' => config('ninja.api_secret'),
    //         'X-API-TOKEN' => $this->token,
    //     ])->putJson('/api/v1/tasks/' . $arr['data']['id'], $data);

    //     $arr = $response->json();

    //     $response->assertStatus(200);

    // }




    public function testTimeLogValidation()
    {
        $data = [
            'time_log' => $this->faker->firstName(),
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/tasks', $data);

        $response->assertStatus(422);

    }

    public function testTimeLogValidation1()
    {
        $data = [
            'timelog' => [[1,2],[3,4]],
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/tasks', $data);

        $arr = $response->json();
        $response->assertStatus(200);
    }



    public function testTimeLogValidation2()
    {
        $data = [
            'timelog' => [],
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/tasks', $data);

        $arr = $response->json();
        $response->assertStatus(200);
    }

    public function testTimeLogValidation3()
    {
        $data = [
            'time_log' => [["a","b",'d'],["c","d",'d']],
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/tasks', $data);

        $response->assertStatus(422);

    }

    public function testTimeLogValidation4()
    {
        $data = [
            'timelog' => [[1,2,'d'],[3,0,'d']],
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/tasks', $data);

        $arr = $response->json();
        $response->assertStatus(200);
    }



    public function testStartTask()
    {
        $log = [
            [2, 1,'d'],
            [10, 20,'d'],
        ];

        $last = end($log);

        $this->assertEquals(10, $last[0]);
        $this->assertEquals(20, $last[1]);

        $new = [time(), 0];

        array_push($log, $new);

        $this->assertEquals(3, count($log));

        //test task is started
        $last = end($log);
        $this->assertTrue($last[1] === 0);

        //stop task
        $last = end($log);
        $last[1] = time();

        $this->assertTrue($last[1] !== 0);
    }

    public function testTaskPost()
    {
        $data = [
            'description' => $this->faker->firstName(),
            'number' => 'taskynumber',
            'client_id' => $this->client->id,
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/tasks', $data);

        $arr = $response->json();
        $response->assertStatus(200);

        $this->assertEquals('taskynumber', $arr['data']['number']);
        $this->assertLessThan(5, strlen($arr['data']['time_log']));

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->put('/api/v1/tasks/'.$arr['data']['id'], $data);

        $response->assertStatus(200);

        try {
            $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token,
            ])->post('/api/v1/tasks', $data);

            $arr = $response->json();
        } catch (ValidationException $e) {
            $response->assertStatus(302);
        }

        $this->assertNotEmpty($arr['data']['number']);
    }

    public function testTaskPostNoDefinedTaskNumber()
    {
        $data = [
            'description' => $this->faker->firstName(),
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/tasks', $data);

        $arr = $response->json();
        $response->assertStatus(200);
        $this->assertNotEmpty($arr['data']['number']);
    }

    public function testTaskWithBadClientId()
    {
        $data = [
            'client_id' => $this->faker->firstName(),
        ];

        try {
            $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token,
            ])->post('/api/v1/tasks', $data);
            $arr = $response->json();
        } catch (ValidationException $e) {
            $response->assertStatus(302);
        }
    }

    public function testTaskPostWithActionStart()
    {
        $data = [
            'description' => $this->faker->firstName(),
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/tasks?action=start', $data);

        $arr = $response->json();
        $response->assertStatus(200);
    }

    public function testTaskPut()
    {
        $data = [
            'description' => $this->faker->firstName(),
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->put('/api/v1/tasks/'.$this->encodePrimaryKey($this->task->id), $data);

        $response->assertStatus(200);
    }

    public function testTasksGet()
    {
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/tasks');

        $response->assertStatus(200);
    }

    public function testTaskGet()
    {
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/tasks/'.$this->encodePrimaryKey($this->task->id));

        $response->assertStatus(200);
    }

    public function testTaskNotArchived()
    {
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/tasks/'.$this->encodePrimaryKey($this->task->id));

        $arr = $response->json();

        $this->assertEquals(0, $arr['data']['archived_at']);
    }

    public function testTaskArchived()
    {
        $data = [
            'ids' => [$this->encodePrimaryKey($this->task->id)],
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/tasks/bulk?action=archive', $data);

        $arr = $response->json();

        $this->assertNotNull($arr['data'][0]['archived_at']);
    }

    public function testTaskRestored()
    {
        $data = [
            'ids' => [$this->encodePrimaryKey($this->task->id)],
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/tasks/bulk?action=restore', $data);

        $arr = $response->json();

        $this->assertEquals(0, $arr['data'][0]['archived_at']);
    }

    public function testTaskDeleted()
    {
        $data = [
            'ids' => [$this->encodePrimaryKey($this->task->id)],
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/tasks/bulk?action=delete', $data);

        $arr = $response->json();

        $this->assertTrue($arr['data'][0]['is_deleted']);
    }

    public function testTaskPostWithStartAction()
    {
        $data = [
            'description' => $this->faker->firstName(),
            'number' => 'taskynumber2',
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/tasks?start=true', $data);

        $arr = $response->json();
        $response->assertStatus(200);

        $this->assertEquals('taskynumber2', $arr['data']['number']);
        $this->assertGreaterThan(5, strlen($arr['data']['time_log']));
    }

    public function testTaskPostWithStopAction()
    {
        $data = [
            'description' => $this->faker->firstName(),
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/tasks?stop=true', $data);

        $arr = $response->json();
        $response->assertStatus(200);

        $this->assertLessThan(5, strlen($arr['data']['time_log']));
    }
}
