<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\Combination;
use App\Models\Level;
use App\Models\Subject;
use App\Models\Term;
use Illuminate\Database\Seeder;

/**
 * All levels of the Rwandan system, the 2026/2027 NESA calendar, and EXAMPLE combinations,
 * trades and subjects. The examples are to be checked against the pilot school's real
 * CAMIS/SDMS codes (design section 2.4) and edited in the setup screens.
 */
class AcademicStructureSeeder extends Seeder
{
    public function run(): void
    {
        $order = 0;
        $levels = [
            ['N1', 'Nursery 1', 'nursery'], ['N2', 'Nursery 2', 'nursery'], ['N3', 'Nursery 3', 'nursery'],
            ['P1', 'Primary 1', 'primary'], ['P2', 'Primary 2', 'primary'], ['P3', 'Primary 3', 'primary'],
            ['P4', 'Primary 4', 'primary'], ['P5', 'Primary 5', 'primary'], ['P6', 'Primary 6', 'primary'],
            ['S1', 'Senior 1', 'o_level'], ['S2', 'Senior 2', 'o_level'], ['S3', 'Senior 3', 'o_level'],
            ['S4', 'Senior 4', 'a_level'], ['S5', 'Senior 5', 'a_level'], ['S6', 'Senior 6', 'a_level'],
            ['L3', 'TVET Level 3 (RQF 3)', 'tvet'], ['L4', 'TVET Level 4 (RQF 4)', 'tvet'], ['L5', 'TVET Level 5 (RQF 5)', 'tvet'],
        ];
        foreach ($levels as [$code, $name, $cycle]) {
            Level::query()->updateOrCreate(['code' => $code], ['name' => $name, 'cycle' => $cycle, 'order_no' => ++$order]);
        }

        // NESA / MINEDUC 2026/2027 school calendar (design section 2.2).
        $year = AcademicYear::query()->updateOrCreate(['name' => '2026-2027'], ['start_date' => '2026-09-07', 'end_date' => '2027-07-02']);
        $terms = [[1, '2026-09-07', '2026-12-17'], [2, '2027-01-14', '2027-04-02'], [3, '2027-04-19', '2027-07-02']];
        foreach ($terms as [$n, $start, $end]) {
            Term::query()->updateOrCreate(['academic_year_id' => $year->id, 'term_number' => $n], ['start_date' => $start, 'end_date' => $end]);
        }
        $today = now()->toDateString();
        $current = Term::query()->where('academic_year_id', $year->id)->where('start_date', '<=', $today)->orderByDesc('start_date')->first()
            ?? Term::query()->where('academic_year_id', $year->id)->orderBy('term_number')->first();
        $current->makeCurrent();

        // Examples: A-level combinations and TVET trades.
        $combinations = [
            ['MCB', 'Mathematics, Chemistry, Biology', 'a_level'], ['MPC', 'Mathematics, Physics, Computer Science', 'a_level'],
            ['PCB', 'Physics, Chemistry, Biology', 'a_level'], ['MEG', 'Mathematics, Economics, Geography', 'a_level'],
            ['HEG', 'History, Economics, Geography', 'a_level'], ['HGL', 'History, Geography, Literature in English', 'a_level'],
            ['SOD', 'Software Development', 'tvet_trade'], ['NIT', 'Networking and Internet Technology', 'tvet_trade'],
            ['ELT', 'Electrical Technology', 'tvet_trade'], ['BDC', 'Building Construction', 'tvet_trade'],
            ['ACC', 'Accounting', 'tvet_trade'], ['TOR', 'Tourism', 'tvet_trade'],
        ];
        foreach ($combinations as [$code, $name, $type]) {
            Combination::query()->updateOrCreate(['code' => $code], ['name' => $name, 'type' => $type]);
        }

        // Examples: competence-based curriculum subjects.
        $subjects = [
            ['MATH', 'Mathematics', 'core'], ['ENG', 'English', 'core'], ['KIN', 'Kinyarwanda', 'core'],
            ['FRE', 'French', 'core'], ['KISW', 'Kiswahili', 'core'], ['SET', 'Science and Elementary Technology', 'core'],
            ['SRS', 'Social and Religious Studies', 'core'], ['CRA', 'Creative Arts', 'core'], ['PES', 'Physical Education and Sports', 'core'],
            ['PHY', 'Physics', 'core'], ['CHE', 'Chemistry', 'core'], ['BIO', 'Biology and Health Sciences', 'core'],
            ['ICT', 'Information and Communication Technology', 'core'], ['GEO', 'Geography and Environment', 'core'],
            ['HIS', 'History and Citizenship', 'core'], ['ENT', 'Entrepreneurship', 'core'], ['ECO', 'Economics', 'core'],
            ['CSC', 'Computer Science', 'core'], ['LIT', 'Literature in English', 'core'], ['REL', 'Religious Education', 'elective'],
        ];
        foreach ($subjects as [$code, $name, $type]) {
            Subject::query()->updateOrCreate(['code' => $code], ['name' => $name, 'course_type' => $type]);
        }
    }
}
