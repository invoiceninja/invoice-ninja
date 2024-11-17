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

use App\Models\Expense;
use Tests\TestCase;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\Quote;
use App\Models\Task;
use Tests\MockAccountData;
use App\Utils\Traits\MakesHash;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\ValidationException;
use Illuminate\Foundation\Testing\DatabaseTransactions;

/**
 * 
 *  App\Http\Controllers\ProjectController
 */
class ProjectApiTest extends TestCase
{
    use MakesHash;
    use DatabaseTransactions;
    use MockAccountData;

    protected $faker;

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();

        Session::start();

        $this->faker = \Faker\Factory::create();

        Model::reguard();
    }

    public function testInvoiceProject()
    {

        $p = Project::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'name' => 'Best Project',
            'task_rate' => 100,
        ]);

        $t = Task::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'project_id' => $p->id,
            'client_id' => $this->client->id,
            'time_log' => '[[1731391977,1731399177,"item description",true],[1731399178,1731499177,"item description 2", true]]',
            'description' => 'Top level Task Description',
        ]);

        $e = Expense::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'project_id' => $p->id,
            'amount' => 100,
            'public_notes' => 'Expensive Business!!',
            'should_be_invoiced' => true,
        ]);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson("/api/v1/projects/{$p->hashed_id}/invoice");

        $response->assertStatus(200);

        $arr = $response->json();

        nlog($arr);
    }



    public function testCreateProjectWithNullTaskRate()
    {

        $data = [
            'client_id' => $this->client->hashed_id,
            'name' => 'howdy',
            'task_rate' => null,
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson("/api/v1/projects", $data);

        $response->assertStatus(200);

        $arr = $response->json();

        $this->assertEquals(0, $arr['data']['task_rate']);

    }

    public function testCreateProjectWithNullTaskRate2()
    {

        $data = [
            'client_id' => $this->client->hashed_id,
            'name' => 'howdy',
            'task_rate' => "A",
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson("/api/v1/projects", $data);

        $response->assertStatus(422);

        $arr = $response->json();

    }


    public function testCreateProjectWithNullTaskRate3()
    {

        $data = [
            'client_id' => $this->client->hashed_id,
            'name' => 'howdy',
            'task_rate' => "10",
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson("/api/v1/projects", $data);

        $response->assertStatus(200);

        $arr = $response->json();

        $this->assertEquals(10, $arr['data']['task_rate']);

    }

    public function testCreateProjectWithNullTaskRate5()
    {

        $data = [
            'client_id' => $this->client->hashed_id,
            'name' => 'howdy',
            'task_rate' => "-10",
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson("/api/v1/projects", $data);

        $response->assertStatus(200);

        $arr = $response->json();

        $this->assertEquals(0, $arr['data']['task_rate']);

    }



    public function testCreateProjectWithNullTaskRate4()
    {

        $data = [
            'client_id' => $this->client->hashed_id,
            'name' => 'howdy',
            'task_rate' => 10,
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson("/api/v1/projects", $data);

        $response->assertStatus(200);

        $arr = $response->json();

        $this->assertEquals(10, $arr['data']['task_rate']);

    }

    public function testProjectIncludesZeroCount()
    {

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->putJson("/api/v1/projects/{$this->project->hashed_id}?include=expenses,invoices,quotes");

        $response->assertStatus(200);

        $arr = $response->json();

        $this->assertEquals(0, count($arr['data']['invoices']));
        $this->assertEquals(0, count($arr['data']['expenses']));
        $this->assertEquals(0, count($arr['data']['quotes']));

    }

    public function testProjectIncludes()
    {
        $i = Invoice::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'client_id' => $this->project->client_id,
            'project_id' => $this->project->id,
        ]);


        $e = Expense::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'client_id' => $this->project->client_id,
            'project_id' => $this->project->id,
        ]);


        $q = Quote::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'client_id' => $this->project->client_id,
            'project_id' => $this->project->id,
        ]);


        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->putJson("/api/v1/projects/{$this->project->hashed_id}?include=expenses,invoices,quotes");

        $response->assertStatus(200);

        $arr = $response->json();

        $this->assertEquals(1, count($arr['data']['invoices']));
        $this->assertEquals(1, count($arr['data']['expenses']));
        $this->assertEquals(1, count($arr['data']['quotes']));

    }

    public function testProjectValidationForBudgetedHoursPut()
    {

        $data = $this->project->toArray();
        $data['budgeted_hours'] = "aa";

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->putJson("/api/v1/projects/{$this->project->hashed_id}", $data);

        $response->assertStatus(422);

    }

    public function testProjectValidationForBudgetedHoursPutNull()
    {

        $data = $this->project->toArray();
        $data['budgeted_hours'] = null;

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->putJson("/api/v1/projects/{$this->project->hashed_id}", $data);

        $response->assertStatus(200);

    }


    public function testProjectValidationForBudgetedHoursPutEmpty()
    {

        $data = $this->project->toArray();
        $data['budgeted_hours'] = "";

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->putJson("/api/v1/projects/{$this->project->hashed_id}", $data);

        $response->assertStatus(200);

    }


    public function testProjectValidationForBudgetedHours()
    {

        $data = [
            'name' => $this->faker->firstName(),
            'client_id' => $this->client->hashed_id,
            'number' => 'duplicate',
            'budgeted_hours' => null
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/projects', $data);

        $response->assertStatus(200);

    }

    public function testProjectValidationForBudgetedHours2()
    {

        $data = [
            'name' => $this->faker->firstName(),
            'client_id' => $this->client->hashed_id,
            'number' => 'duplicate',
            'budgeted_hours' => "a"
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/projects', $data);

        $response->assertStatus(422);

    }

    public function testProjectValidationForBudgetedHours3()
    {

        $data = [
            'name' => $this->faker->firstName(),
            'client_id' => $this->client->hashed_id,
            'number' => 'duplicate',
            'budgeted_hours' => ""
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/projects', $data);

        $response->assertStatus(200);

    }

    public function testProjectGetFilter()
    {
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/projects?filter=xx');

        $response->assertStatus(200);
    }

    public function testProjectGet()
    {
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/projects/'.$this->encodePrimaryKey($this->project->id));

        $response->assertStatus(200);
    }

    public function testProjectPost()
    {
        $data = [
            'name' => $this->faker->firstName(),
            'client_id' => $this->client->hashed_id,
            'number' => 'duplicate',
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/projects', $data);

        $response->assertStatus(200);

        $arr = $response->json();

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->put('/api/v1/projects/'.$arr['data']['id'], $data)->assertStatus(200);

        try {
            $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token,
            ])->post('/api/v1/projects', $data);
        } catch (ValidationException $e) {
            $response->assertStatus(302);
        }
    }

    public function testProjectPostFilters()
    {
        $data = [
            'name' => 'Sherlock',
            'client_id' => $this->client->hashed_id,
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/projects', $data);

        $response->assertStatus(200);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/projects?filter=Sherlock');

        $arr = $response->json();

        $this->assertEquals(1, count($arr['data']));
    }

    public function testProjectPut()
    {
        $data = [
            'name' => $this->faker->firstName(),
            'public_notes' => 'Coolio',
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->put('/api/v1/projects/'.$this->encodePrimaryKey($this->project->id), $data);

        $response->assertStatus(200);
    }

    public function testProjectNotArchived()
    {
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/projects/'.$this->encodePrimaryKey($this->project->id));

        $arr = $response->json();

        $this->assertEquals(0, $arr['data']['archived_at']);
    }

    public function testProjectArchived()
    {
        $data = [
            'ids' => [$this->encodePrimaryKey($this->project->id)],
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/projects/bulk?action=archive', $data);

        $arr = $response->json();

        $this->assertNotNull($arr['data'][0]['archived_at']);
    }

    public function testProjectRestored()
    {
        $data = [
            'ids' => [$this->encodePrimaryKey($this->project->id)],
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/projects/bulk?action=restore', $data);

        $arr = $response->json();

        $this->assertEquals(0, $arr['data'][0]['archived_at']);
    }

    public function testProjectDeleted()
    {
        $data = [
            'ids' => [$this->encodePrimaryKey($this->project->id)],
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/projects/bulk?action=delete', $data);

        $arr = $response->json();

        $this->assertTrue($arr['data'][0]['is_deleted']);
    }
}
