<?php

use Encore\Admin\Auth\Database\Administrator;
use Tests\Models\Profile as ProfileModel;
use Tests\Models\User as UserModel;

class UserGridTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->be(Administrator::first(), 'admin');
    }

    public function testIndexPage()
    {
        $this->visit('admin/users')
            ->see('Users')
            ->seeInElement('tr th', 'Username')
            ->seeInElement('tr th', 'Email')
            ->seeInElement('tr th', 'Mobile')
            ->seeInElement('tr th', 'Full name')
            ->seeInElement('tr th', 'Avatar')
            ->seeInElement('tr th', 'Post code')
            ->seeInElement('tr th', 'Address')
            ->seeInElement('tr th', 'Position')
            ->seeInElement('tr th', 'Color')
            ->seeInElement('tr th', '开始时间')
            ->seeInElement('tr th', '结束时间')
            ->seeInElement('tr th', 'Color')
            ->seeInElement('tr th', 'Created at')
            ->seeInElement('tr th', 'Updated at');

        $action = url('/admin/users');

        $this->seeElement("form[action='$action'][method=get]")
            ->seeElement("form[action='$action'][method=get] input[name=id]")
            ->seeElement("form[action='$action'][method=get] input[name=username]")
            ->seeElement("form[action='$action'][method=get] input[name=email]")
            ->seeElement("form[action='$action'][method=get] input[name='profile[start_at][start]']")
            ->seeElement("form[action='$action'][method=get] input[name='profile[start_at][end]']")
            ->seeElement("form[action='$action'][method=get] input[name='profile[end_at][start]']")
            ->seeElement("form[action='$action'][method=get] input[name='profile[end_at][end]']");

        $urlAll = url('/admin/users?_export_=all');
        $urlNew = url('/admin/users/create');
        $this->seeInElement("a[href=\"{$urlAll}\"]", 'All')
            ->seeInElement("a[href=\"{$urlNew}\"]", 'New');
    }

    protected function seedsTable($count = 100)
    {
        \Tests\Models\User::factory()->count($count)
            ->create()
            ->each(function ($u) {
                $u->profile()->save(\Tests\Models\Profile::factory()->make());
                $u->tags()->saveMany(\Tests\Models\Tag::factory()->count(5)->make());
                $u->data = ['json' => ['field' => random_int(0, 50)]];
                $u->save();
            });
    }

    public function testGridWithData()
    {
        $this->seedsTable();

        $this->visit('admin/users')
            ->see('Users');

        $this->assertCount(100, UserModel::all());
        $this->assertCount(100, ProfileModel::all());
    }

    public function testGridPagination()
    {
        $this->seedsTable(65);

        $this->visit('admin/users')
            ->see('Users');

        $this->visit('admin/users?page=2');
        $this->assertCount(20, $this->crawler()->filter('td a i[class*=fa-edit]'));

        $this->visit('admin/users?page=3');
        $this->assertCount(20, $this->crawler()->filter('td a i[class*=fa-edit]'));

        $this->visit('admin/users?page=4');
        $this->assertCount(5, $this->crawler()->filter('td a i[class*=fa-edit]'));

        $this->click(1)->seePageIs('admin/users?page=1');
        $this->assertCount(20, $this->crawler()->filter('td a i[class*=fa-edit]'));
    }

    public function testOrderByJson()
    {
        $this->seedsTable(10);
        $this->assertCount(10, UserModel::all());

        $this->visit('admin/users?_sort[column]=data.json.field&_sort[type]=desc&_sort[cast]=unsigned');

        $jsonTds = $this->crawler->filter('table.table tbody td.column-data-json-field');
        $this->assertCount(10, $jsonTds);
        $prevValue = PHP_INT_MAX;
        foreach ($jsonTds as $jsonTd) {
            $currentValue = (int) $jsonTd->nodeValue;
            $this->assertTrue($currentValue <= $prevValue);
            $prevValue = $currentValue;
        }
    }

    /**
     * The sort comes from the query string and is written in the query.
     */
    public function testSortInputIsNotWrittenInTheQuery()
    {
        $this->seedsTable(3);

        $payloads = [
            '_sort[column]=id&_sort[type]=desc,(select sleep(5))',
            "_sort[column]=data.json.field')),(select sleep(5)),JSON_EXTRACT(data,'$&_sort[type]=desc",
            '_sort[column]=data.json.field&_sort[type]=desc&_sort[cast]=unsigned)),(select sleep(5)',
            '_sort[column][]=id&_sort[type]=desc',
        ];

        foreach ($payloads as $payload) {
            \DB::enableQueryLog();

            $this->get('admin/users?'.$payload);

            $this->assertResponseOk();

            foreach (\DB::getQueryLog() as $query) {
                $this->assertStringNotContainsStringIgnoringCase('sleep', $query['query']);
            }

            \DB::flushQueryLog();
        }
    }

    public function testSortWithAllowedValues()
    {
        $this->seedsTable(3);

        $this->get('admin/users?_sort[column]=id&_sort[type]=DESC');
        $this->assertResponseOk();

        $this->get('admin/users?_sort[column]=data.json.field&_sort[type]=asc&_sort[cast]=decimal(10,2)');
        $this->assertResponseOk();
    }

    public function testEqualFilter()
    {
        $this->seedsTable(50);

        $this->visit('admin/users')
            ->see('Users');

        $this->assertCount(50, UserModel::all());
        $this->assertCount(50, ProfileModel::all());

        $id = rand(1, 50);

        $user = UserModel::find($id);

        $this->visit('admin/users?id='.$id)
            ->seeInElement('td', $user->username)
            ->seeInElement('td', $user->email)
            ->seeInElement('td', $user->mobile)
            ->seeElement("img[src='{$user->avatar}']")
            ->seeInElement('td', "{$user->profile->first_name} {$user->profile->last_name}")
            ->seeInElement('td', $user->profile->postcode)
            ->seeInElement('td', $user->profile->address)
            ->seeInElement('td', "{$user->profile->latitude} {$user->profile->longitude}")
            ->seeInElement('td', $user->profile->color)
            ->seeInElement('td', $user->profile->start_at)
            ->seeInElement('td', $user->profile->end_at);
    }

    public function testLikeFilter()
    {
        $this->seedsTable(50);

        $this->visit('admin/users')
            ->see('Users');

        $this->assertCount(50, UserModel::all());
        $this->assertCount(50, ProfileModel::all());

        $users = UserModel::where('username', 'like', '%mi%')->get();

        $this->visit('admin/users?username=mi');

        $this->assertCount($this->crawler()->filter('table tr')->count() - 1, $users);

        foreach ($users as $user) {
            $this->seeInElement('td', $user->username);
        }
    }

    public function testFilterRelation()
    {
        $this->seedsTable(50);

        $user = UserModel::with('profile')->find(rand(1, 50));

        $this->visit('admin/users?email='.$user->email)
            ->seeInElement('td', $user->username)
            ->seeInElement('td', $user->email)
            ->seeInElement('td', $user->mobile)
            ->seeElement("img[src='{$user->avatar}']")
            ->seeInElement('td', "{$user->profile->first_name} {$user->profile->last_name}")
            ->seeInElement('td', $user->profile->postcode)
            ->seeInElement('td', $user->profile->address)
            ->seeInElement('td', "{$user->profile->latitude} {$user->profile->longitude}")
            ->seeInElement('td', $user->profile->color)
            ->seeInElement('td', $user->profile->start_at)
            ->seeInElement('td', $user->profile->end_at);
    }

    public function testDisplayCallback()
    {
        $this->seedsTable(1);

        $user = UserModel::with('profile')->find(1);

        $this->visit('admin/users')
            ->seeInElement('th', 'Column1 not in table')
            ->seeInElement('th', 'Column2 not in table')
            ->seeInElement('td', "full name:{$user->profile->first_name} {$user->profile->last_name}")
            ->seeInElement('td', "{$user->email}#{$user->profile->color}");
    }

    public function testHasManyRelation()
    {
        \Tests\Models\User::factory()->count(10)
            ->create()
            ->each(function ($u) {
                $u->profile()->save(\Tests\Models\Profile::factory()->make());
                $u->tags()->saveMany(\Tests\Models\Tag::factory()->count(5)->make());
            });

        $this->visit('admin/users')
            ->seeElement('td code');

        $this->assertCount(50, $this->crawler()->filter('td code'));
    }

    public function testGridActions()
    {
        $this->seedsTable(15);

        $this->visit('admin/users');

        $this->assertCount(15, $this->crawler()->filter('td a i[class*=fa-edit]'));
        $this->assertCount(15, $this->crawler()->filter('td a i[class*=fa-trash]'));
    }

    public function testGridRows()
    {
        $this->seedsTable(10);

        $this->visit('admin/users')
            ->seeInElement('td a[class*=btn]', 'detail');

        $this->assertCount(5, $this->crawler()->filter('td a[class*=btn]'));
    }

    public function testGridPerPage()
    {
        $this->seedsTable(98);

        $this->visit('admin/users')
            ->seeElement('select[class*=per-page][name=per-page]')
            ->seeInElement('select option', 10)
            ->seeInElement('select option[selected]', 20)
            ->seeInElement('select option', 30)
            ->seeInElement('select option', 50)
            ->seeInElement('select option', 100);

        $this->assertEquals('http://localhost:8000/admin/users?per_page=20', $this->crawler()->filter('select option[selected]')->attr('value'));

        $perPage = rand(1, 98);

        $this->visit('admin/users?per_page='.$perPage)
            ->seeInElement('select option[selected]', $perPage)
            ->assertCount($perPage + 1, $this->crawler()->filter('tr'));
    }
}
