<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Combination;
use App\Models\Level;
use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\SchoolProfile;
use App\Models\Subject;
use App\Models\Term;
use App\Models\User;
use Database\Seeders\AccessSeeder;
use Database\Seeders\AcademicStructureSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SetupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([AccessSeeder::class, AcademicStructureSeeder::class]);
        SchoolProfile::query()->create(['name' => 'GS Kagombyi', 'short_name' => 'GS Kagombyi', 'school_type' => 'mixed', 'district' => 'X', 'sector' => 'Y']);
    }

    private function userWithRole(string $role): User
    {
        return User::factory()->create(['role_id' => Role::query()->where('name', $role)->value('id')]);
    }

    public function test_school_name_and_colours_come_from_settings_on_every_page(): void
    {
        $head = $this->userWithRole('headteacher');

        $this->actingAs($head)->post(route('setup.settings.update'), [
            'name' => 'Groupe Scolaire Test', 'short_name' => 'GS Test', 'school_type' => 'secondary',
            'district' => 'Kamonyi', 'sector' => 'Runda', 'brand_color' => '#1D4ED8', 'accent_color' => '#F59E0B',
            'default_language' => 'fr', 'sms_sender' => 'GSTEST',
        ])->assertSessionHasNoErrors();

        $this->actingAs($head)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
            ->where('school.name', 'Groupe Scolaire Test')
            ->where('school.short_name', 'GS Test')
            ->where('school.brand_color', '#1D4ED8'));

        // Guests see the new name and the default language on the login page.
        $this->post('/logout');
        $this->get('/login')->assertInertia(fn (Assert $page) => $page->where('school.name', 'Groupe Scolaire Test')->where('locale', 'fr'));
    }

    public function test_an_academic_year_has_three_terms_that_do_not_overlap(): void
    {
        $head = $this->userWithRole('headteacher');
        $terms = [
            ['start_date' => '2027-09-06', 'end_date' => '2027-12-17'],
            ['start_date' => '2027-12-01', 'end_date' => '2028-04-01'],
            ['start_date' => '2028-04-19', 'end_date' => '2028-07-02'],
        ];

        $this->actingAs($head)->post(route('setup.calendar.store'), ['name' => '2027-2028', 'terms' => $terms])
            ->assertSessionHasErrors();
        $this->assertDatabaseMissing('academic_years', ['name' => '2027-2028']);

        $terms[1]['start_date'] = '2028-01-10';
        $this->actingAs($head)->post(route('setup.calendar.store'), ['name' => '2027-2028', 'terms' => $terms])
            ->assertSessionHasNoErrors();
        $this->assertSame(3, AcademicYear::query()->where('name', '2027-2028')->first()->terms()->count());
    }

    public function test_only_one_term_is_current(): void
    {
        $head = $this->userWithRole('headteacher');
        $term2 = Term::query()->where('term_number', 2)->first();

        $this->actingAs($head)->post(route('setup.terms.current', $term2))->assertSessionHasNoErrors();

        $this->assertSame([$term2->id], Term::query()->where('is_current', true)->pluck('id')->all());

        // The database itself refuses a second current term (BR-02).
        $this->expectException(QueryException::class);
        Term::query()->where('term_number', 3)->update(['is_current' => true]);
    }

    public function test_class_name_is_built_and_a_level_needs_a_combination(): void
    {
        $dos = $this->userWithRole('dos');
        $year = AcademicYear::query()->where('is_current', true)->first();
        $s4 = Level::query()->where('cycle', 'a_level')->orderBy('order_no')->first();
        $combo = Combination::query()->where('type', 'a_level')->first();

        $this->actingAs($dos)->post(route('setup.classes.store'), ['academic_year_id' => $year->id, 'level_id' => $s4->id, 'stream' => 'a'])
            ->assertSessionHasErrors('combination_id');

        $this->actingAs($dos)->post(route('setup.classes.store'), ['academic_year_id' => $year->id, 'level_id' => $s4->id, 'combination_id' => $combo->id, 'stream' => 'a'])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('classes', ['name' => "{$s4->code} {$combo->code} A"]);

        // Same class twice in one year is refused.
        $this->actingAs($dos)->post(route('setup.classes.store'), ['academic_year_id' => $year->id, 'level_id' => $s4->id, 'combination_id' => $combo->id, 'stream' => 'A'])
            ->assertSessionHasErrors();
    }

    public function test_subjects_can_be_copied_between_classes(): void
    {
        $dos = $this->userWithRole('dos');
        $year = AcademicYear::query()->where('is_current', true)->first();
        $s1 = Level::query()->where('cycle', 'o_level')->orderBy('order_no')->first();
        $a = SchoolClass::query()->create(['academic_year_id' => $year->id, 'level_id' => $s1->id, 'stream' => 'A', 'name' => SchoolClass::makeName($s1, null, 'A')]);
        $b = SchoolClass::query()->create(['academic_year_id' => $year->id, 'level_id' => $s1->id, 'stream' => 'B', 'name' => SchoolClass::makeName($s1, null, 'B')]);
        foreach (Subject::query()->take(3)->pluck('id') as $id) {
            $a->classSubjects()->create(['subject_id' => $id]);
        }

        $this->actingAs($dos)->post(route('setup.classes.subjects.copy', $b), ['from_class_id' => $a->id])->assertSessionHasNoErrors();

        $this->assertSame(3, $b->classSubjects()->count());
    }

    public function test_permissions_are_enforced(): void
    {
        $teacher = $this->userWithRole('teacher');
        $parent = $this->userWithRole('parent');

        $this->actingAs($teacher)->get(route('setup.classes'))->assertOk();
        $this->actingAs($teacher)->get(route('setup.settings'))->assertForbidden();
        $this->actingAs($teacher)->post(route('setup.classes.store'), [])->assertForbidden();
        $this->actingAs($teacher)->get(route('setup.users'))->assertForbidden();
        $this->actingAs($parent)->get(route('setup.classes'))->assertForbidden();
    }

    public function test_only_an_admin_can_create_an_admin(): void
    {
        $head = $this->userWithRole('headteacher');
        $adminRole = Role::query()->where('name', 'admin')->value('id');

        $this->actingAs($head)->post(route('setup.users.store'), [
            'name' => 'Someone', 'username' => 'someone', 'role_id' => $adminRole, 'language' => 'en',
            'password' => 'secret123', 'password_confirmation' => 'secret123',
        ])->assertSessionHasErrors('role_id');
        $this->assertDatabaseMissing('users', ['username' => 'someone']);
    }

    public function test_every_setup_page_renders(): void
    {
        $admin = $this->userWithRole('admin');
        $year = AcademicYear::query()->where('is_current', true)->first();
        $level = Level::query()->where('cycle', 'primary')->first();
        $class = SchoolClass::query()->create(['academic_year_id' => $year->id, 'level_id' => $level->id, 'name' => SchoolClass::makeName($level, null, null)]);

        foreach (['dashboard', 'profile.edit', 'setup.settings', 'setup.calendar', 'setup.levels', 'setup.combinations', 'setup.subjects',
            'setup.topics', 'setup.staff', 'setup.classes', 'setup.users'] as $name) {
            $this->actingAs($admin)->get(route($name))->assertOk();
        }
        $this->actingAs($admin)->get(route('setup.classes.subjects', $class))->assertOk();

        foreach (['fr', 'rw'] as $locale) {
            $this->actingAs($admin)->post(route('language'), ['locale' => $locale]);
            $this->actingAs($admin)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page->where('locale', $locale)->has('translations.Dashboard'));
        }
    }
}
