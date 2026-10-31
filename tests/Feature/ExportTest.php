<?php

namespace Tests\Feature;

use App\Exports\AggregateExport;
use App\Models\ReportingPeriod;
use App\Models\Resident;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class ExportTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_exports_are_authorized_aggregate_files_and_use_locked_snapshot(): void
    {
        $this->actingAs($this->createStaff('bendahara'));
        $period = ReportingPeriod::factory()->create();
        $this->get('/rekapitulasi/'.$period->id.'/ekspor?type=adults&format=xlsx')->assertForbidden();
        $this->actingAs($this->createSuperadmin());
        $resident = Resident::factory()->create(['full_name' => 'RAHASIA Identitas sintetis']);
        $period->targets()->create([
            'resident_id' => $resident->id,
            'category_code' => 'adult',
            'attendance_status' => 'absent',
        ]);
        $this->patch(
            '/rekapitulasi/'.$period->id,
            ['revision' => 1, 'action' => 'submit', 'reason' => 'Pengajuan rekap sintetis']
        )->assertRedirect();
        $this->patch('/rekapitulasi/'.$period->id, [
            'revision' => $period->fresh()->revision,
            'action' => 'approve',
            'reason' => 'Persetujuan sintetis',
        ])->assertRedirect();
        $response = $this->get('/rekapitulasi/'.$period->id.'/ekspor?type=adults&format=xlsx');
        $response->assertOk()->assertDownload('jeruk-2026-1-adults.xlsx');
        $workbook = IOFactory::load($response->baseResponse->getFile()->getPathname());
        $values = $workbook->getActiveSheet()->toArray(null, true, false);
        $text = json_encode($values);
        $this->assertStringNotContainsString($resident->full_name, $text);
        $this->assertStringContainsString('Sasaran unik', $text);
        $this->assertSame(1, collect($values)->first(fn ($row) => $row[0] === 'Sasaran unik')[1]);
        $pdf = $this->get('/rekapitulasi/'.$period->id.'/ekspor?type=adults&format=pdf');
        $pdf->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $pdf->getContent());
        $this->get('/rekapitulasi/'.$period->id.'/ekspor?type=unknown&format=xlsx')->assertSessionHasErrors('type');
    }

    public function test_export_text_cannot_become_spreadsheet_formulas(): void
    {
        $export = new AggregateExport(['Sumber' => '=HYPERLINK("bad")'], []);
        $this->assertSame("'=HYPERLINK(\"bad\")", $export->array()[1][1]);
    }
}
