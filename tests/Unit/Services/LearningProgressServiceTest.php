<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Enums\ContentStatus;
use App\Models\Certification;
use App\Models\Chapter;
use App\Models\Enrollment;
use App\Models\Part;
use App\Models\Section;
use App\Models\SectionProgress;
use App\Services\LearningProgressService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LearningProgressServiceTest extends TestCase
{
    use RefreshDatabase;

    private LearningProgressService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new LearningProgressService;
    }

    public function test_summarize_returns_all_zeros_when_certification_has_no_content(): void
    {
        $certification = Certification::factory()->published()->create();
        $enrollment = Enrollment::factory()->for($certification)->learning()->create();

        $summary = $this->service->summarize($enrollment);

        $this->assertSame(0, $summary->sectionsTotal);
        $this->assertSame(0, $summary->sectionsCompleted);
        $this->assertSame(0.0, $summary->sectionCompletionRatio);
        $this->assertSame(0, $summary->chaptersTotal);
        $this->assertSame(0, $summary->partsTotal);
        $this->assertSame(0.0, $summary->overallCompletionRatio);
    }

    public function test_summarize_computes_full_completion_across_all_tiers(): void
    {
        $certification = Certification::factory()->published()->create();
        $enrollment = Enrollment::factory()->for($certification)->learning()->create();
        $part = Part::factory()->forCertification($certification)->published()->create();
        $chapter = Chapter::factory()->forPart($part)->published()->create();
        $section = Section::factory()->forChapter($chapter)->published()->create();
        SectionProgress::factory()->forEnrollment($enrollment)->forSection($section)->create();

        $summary = $this->service->summarize($enrollment);

        $this->assertSame(1, $summary->sectionsTotal);
        $this->assertSame(1, $summary->sectionsCompleted);
        $this->assertSame(1.0, $summary->sectionCompletionRatio);
        $this->assertSame(1, $summary->chaptersCompleted);
        $this->assertSame(1, $summary->partsCompleted);
        $this->assertSame(1.0, $summary->chapterCompletionRatio);
        $this->assertSame(1.0, $summary->partCompletionRatio);
        $this->assertSame(1.0, $summary->overallCompletionRatio);
    }

    public function test_summarize_treats_a_chapter_as_incomplete_until_every_section_is_done(): void
    {
        $certification = Certification::factory()->published()->create();
        $enrollment = Enrollment::factory()->for($certification)->learning()->create();
        $part = Part::factory()->forCertification($certification)->published()->create();
        $chapter = Chapter::factory()->forPart($part)->published()->create();
        $doneSection = Section::factory()->forChapter($chapter)->published()->create();
        Section::factory()->forChapter($chapter)->published()->create(); // 未読了のまま残す
        SectionProgress::factory()->forEnrollment($enrollment)->forSection($doneSection)->create();

        $summary = $this->service->summarize($enrollment);

        $this->assertSame(2, $summary->sectionsTotal);
        $this->assertSame(1, $summary->sectionsCompleted);
        $this->assertSame(0.5, $summary->sectionCompletionRatio);
        $this->assertSame(0, $summary->chaptersCompleted, 'Chapter 配下の Section が全て読了でない限り完了とみなさない');
        $this->assertSame(0, $summary->partsCompleted);
    }

    public function test_summarize_excludes_unpublished_content_from_totals(): void
    {
        $certification = Certification::factory()->published()->create();
        $enrollment = Enrollment::factory()->for($certification)->learning()->create();
        $publishedPart = Part::factory()->forCertification($certification)->published()->create();
        $publishedChapter = Chapter::factory()->forPart($publishedPart)->published()->create();
        Section::factory()->forChapter($publishedChapter)->published()->create();

        // 下書きの Part / Chapter / Section は集計対象に含めない
        $draftPart = Part::factory()->forCertification($certification)->state(['status' => ContentStatus::Draft->value])->create();
        $draftChapter = Chapter::factory()->forPart($draftPart)->state(['status' => ContentStatus::Draft->value])->create();
        Section::factory()->forChapter($draftChapter)->state(['status' => ContentStatus::Draft->value])->create();
        // 公開 Chapter 配下の下書き Section も除外する
        Section::factory()->forChapter($publishedChapter)->state(['status' => ContentStatus::Draft->value])->create();

        $summary = $this->service->summarize($enrollment);

        $this->assertSame(1, $summary->partsTotal);
        $this->assertSame(1, $summary->chaptersTotal);
        $this->assertSame(1, $summary->sectionsTotal);
    }

    public function test_overall_completion_ratio_mirrors_section_completion_ratio(): void
    {
        $certification = Certification::factory()->published()->create();
        $enrollment = Enrollment::factory()->for($certification)->learning()->create();
        $part = Part::factory()->forCertification($certification)->published()->create();
        $chapter = Chapter::factory()->forPart($part)->published()->create();
        Section::factory()->forChapter($chapter)->published()->count(4)->create();
        $sections = Section::query()->where('chapter_id', $chapter->id)->get();
        SectionProgress::factory()->forEnrollment($enrollment)->forSection($sections->first())->create();

        $summary = $this->service->summarize($enrollment);

        $this->assertSame($summary->sectionCompletionRatio, $summary->overallCompletionRatio);
        $this->assertSame(0.25, $summary->overallCompletionRatio);
    }

    public function test_batch_section_ratios_returns_empty_array_for_empty_collection(): void
    {
        $result = $this->service->batchSectionRatios(collect());

        $this->assertSame([], $result);
    }

    public function test_batch_section_ratios_computes_independently_per_enrollment(): void
    {
        $certificationA = Certification::factory()->published()->create();
        $partA = Part::factory()->forCertification($certificationA)->published()->create();
        $chapterA = Chapter::factory()->forPart($partA)->published()->create();
        Section::factory()->forChapter($chapterA)->published()->count(2)->create();
        $sectionsA = Section::query()->where('chapter_id', $chapterA->id)->get();
        $enrollmentA = Enrollment::factory()->for($certificationA)->learning()->create();
        SectionProgress::factory()->forEnrollment($enrollmentA)->forSection($sectionsA->first())->create();

        $certificationB = Certification::factory()->published()->create();
        $partB = Part::factory()->forCertification($certificationB)->published()->create();
        $chapterB = Chapter::factory()->forPart($partB)->published()->create();
        $sectionB = Section::factory()->forChapter($chapterB)->published()->create();
        $enrollmentB = Enrollment::factory()->for($certificationB)->learning()->create();
        SectionProgress::factory()->forEnrollment($enrollmentB)->forSection($sectionB)->create();

        // 教材を一切持たない資格の Enrollment(0 除算にならず 0.0 を返すことを確認)
        $certificationC = Certification::factory()->published()->create();
        $enrollmentC = Enrollment::factory()->for($certificationC)->learning()->create();

        $result = $this->service->batchSectionRatios(collect([$enrollmentA, $enrollmentB, $enrollmentC]));

        $this->assertSame(0.5, $result[$enrollmentA->id]);
        $this->assertSame(1.0, $result[$enrollmentB->id]);
        $this->assertSame(0.0, $result[$enrollmentC->id]);
    }
}
