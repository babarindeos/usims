<?php

namespace Tests\Unit;

use App\Modules\Notifications\Models\NotificationTemplate;
use App\Modules\Workflow\Models\WorkflowDefinition;
use PHPUnit\Framework\TestCase;

class TemplateAndConditionTest extends TestCase
{
    public function test_template_placeholders_are_filled_and_unknown_ones_left_blank(): void
    {
        $this->assertSame(
            'Your submission WF-1 was approved. ',
            NotificationTemplate::render('Your submission {{ reference }} was {{outcome}}. {{ missing }}', ['reference' => 'WF-1', 'outcome' => 'approved'])
        );
    }

    public function test_workflow_conditions_must_all_match(): void
    {
        $definition = new WorkflowDefinition(['conditions' => ['department' => 'CSC', 'level' => 300]]);

        $this->assertTrue($definition->matches(['department' => 'CSC', 'level' => '300', 'mode' => 'full_time']));
        $this->assertFalse($definition->matches(['department' => 'CSC']));
        $this->assertFalse($definition->matches(['department' => 'MTS', 'level' => 300]));
        $this->assertTrue((new WorkflowDefinition)->matches([]));
    }
}
