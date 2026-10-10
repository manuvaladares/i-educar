<?php

namespace Tests\Unit\App\Models;

use App\Models\Employee;
use App\Models\LegacyDeficiency;
use App\Models\LegacyIndividual;
use App\Models\LegacyPerson;
use App\Models\LegacyPhone;
use Database\Factories\LegacyIndividualFactory;
use Tests\EloquentTestCase;

class LegacyPersonTest extends EloquentTestCase
{
    protected $relations = [
        'phone' => LegacyPhone::class,
        'individual' => LegacyIndividual::class,
        'employee' => Employee::class,
        'childrenAsMother' => LegacyIndividual::class,
        'childrenAsFather' => LegacyIndividual::class,
        'dependentsAsResponsible' => LegacyIndividual::class,
    ];

    protected function getEloquentModelName(): string
    {
        return LegacyPerson::class;
    }

    public function test_attributes()
    {
        $this->assertEquals($this->model->id, $this->model->idpes);
        $this->assertEquals($this->model->name, $this->model->nome);
    }

    public function test_relationship_deficiencies(): void
    {
        LegacyIndividualFactory::new()->hasDeficiency()->create(['idpes' => $this->model]);

        $this->assertCount(1, $this->model->deficiencies);
        $this->assertInstanceOf(LegacyDeficiency::class, $this->model->deficiencies->first());
    }

    public function test_relationship_considerable_deficiencies(): void
    {
        LegacyIndividualFactory::new()->hasDeficiency(['desconsidera_regra_diferenciada' => false])->create(['idpes' => $this->model]);

        $this->assertCount(1, $this->model->considerableDeficiencies);
        $this->assertInstanceOf(LegacyDeficiency::class, $this->model->considerableDeficiencies->first());
    }

    public function test_relationships_with_children_and_dependents(): void
    {
        $childWithMother = LegacyIndividualFactory::new()->create([
            'idpes_mae' => $this->model->getKey(),
        ]);
        $childWithFather = LegacyIndividualFactory::new()->create([
            'idpes_pai' => $this->model->getKey(),
        ]);
        $dependent = LegacyIndividualFactory::new()->create([
            'idpes_responsavel' => $this->model->getKey(),
        ]);

        $this->assertTrue($this->model->childrenAsMother->contains($childWithMother));
        $this->assertTrue($this->model->childrenAsFather->contains($childWithFather));
        $this->assertTrue($this->model->dependentsAsResponsible->contains($dependent));
    }
}
