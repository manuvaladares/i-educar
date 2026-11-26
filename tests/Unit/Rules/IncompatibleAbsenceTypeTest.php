<?php

use App\Rules\IncompatibleAbsenceType;
use Database\Factories\LegacyEvaluationRuleFactory;
use Database\Factories\LegacyEvaluationRuleGradeYearFactory;
use Database\Factories\LegacyGradeFactory;
use Database\Factories\LegacySchoolClassFactory;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

uses(TestCase::class, DatabaseTransactions::class);

beforeEach(function () {
    $this->createAbsenceRule = function (int $gradeId, int $type, int $year = 2030): void {
        $evaluationRule = LegacyEvaluationRuleFactory::new()->create(['tipo_presenca' => $type]);
        LegacyEvaluationRuleGradeYearFactory::new()->create([
            'serie_id' => $gradeId,
            'regra_avaliacao_id' => $evaluationRule,
            'regra_avaliacao_diferenciada_id' => $evaluationRule,
            'ano_letivo' => $year,
        ]);
    };

    $this->createSelection = function (array $types): array {
        $schoolClass = LegacySchoolClassFactory::new()->create(['ano' => 2030]);
        $selection = [];
        foreach ($types as $index => $type) {
            $grade = $index === 0 ? $schoolClass->grade : LegacyGradeFactory::new()->create();
            ($this->createAbsenceRule)($grade->getKey(), $type);
            $selection[] = ['turma_id' => $schoolClass->getKey(), 'serie_id' => $grade->getKey()];
        }

        return $selection;
    };
});

test('aceita séries com o mesmo tipo de apuração de presença', function (array $types) {
    $selection = ($this->createSelection)($types);

    expect((new IncompatibleAbsenceType)->passes('series', $selection))->toBeTrue();
})->with([
    'duas séries com presença geral' => [[RegraAvaliacao_Model_TipoPresenca::GERAL, RegraAvaliacao_Model_TipoPresenca::GERAL]],
    'duas séries com presença por componente' => [[RegraAvaliacao_Model_TipoPresenca::POR_COMPONENTE, RegraAvaliacao_Model_TipoPresenca::POR_COMPONENTE]],
    'uma única série com presença geral' => [[RegraAvaliacao_Model_TipoPresenca::GERAL]],
    'uma única série com presença por componente' => [[RegraAvaliacao_Model_TipoPresenca::POR_COMPONENTE]],
]);

test('recusa tipos de presença incompatíveis e explica o erro no campo validado', function () {
    $selection = ($this->createSelection)([RegraAvaliacao_Model_TipoPresenca::GERAL, RegraAvaliacao_Model_TipoPresenca::POR_COMPONENTE]);
    $validator = Validator::make(['series' => $selection], ['series' => [new IncompatibleAbsenceType]]);

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->first('series'))
        ->toBe('As séries selecionadas devem possuir o mesmo tipo de apuração de presença (geral ou por componente).');
});

test('recusa a seleção vazia', function ($selection) {
    expect((new IncompatibleAbsenceType)->passes('series', $selection))->toBeFalse();
})->with(['lista vazia' => [[]], 'valor nulo' => [null]]);

test('ignora regras incompatíveis de séries não selecionadas ou de outro ano', function (bool $otherYear) {
    $selection = ($this->createSelection)([RegraAvaliacao_Model_TipoPresenca::GERAL, RegraAvaliacao_Model_TipoPresenca::GERAL]);
    $gradeId = $otherYear ? $selection[0]['serie_id'] : LegacyGradeFactory::new()->create()->getKey();
    ($this->createAbsenceRule)($gradeId, RegraAvaliacao_Model_TipoPresenca::POR_COMPONENTE, $otherYear ? 2029 : 2030);

    expect((new IncompatibleAbsenceType)->passes('series', $selection))->toBeTrue();
})->with(['outra série' => [false], 'outro ano letivo' => [true]]);

test('recusa a seleção sem regra de avaliação no ano da turma', function (bool $previousYear) {
    $schoolClass = LegacySchoolClassFactory::new()->create(['ano' => 2030]);
    if ($previousYear) {
        ($this->createAbsenceRule)($schoolClass->grade->getKey(), RegraAvaliacao_Model_TipoPresenca::GERAL, 2029);
    }

    expect((new IncompatibleAbsenceType)->passes('series', [
        ['turma_id' => $schoolClass->getKey(), 'serie_id' => $schoolClass->grade->getKey()],
    ]))->toBeFalse();
})->with(['nenhuma regra' => [false], 'regra apenas no ano anterior' => [true]]);

test('a repetição e a ordem das séries não tornam tipos iguais incompatíveis', function () {
    $selection = ($this->createSelection)([RegraAvaliacao_Model_TipoPresenca::GERAL, RegraAvaliacao_Model_TipoPresenca::GERAL]);

    expect((new IncompatibleAbsenceType)->passes('series', [$selection[1], $selection[0], $selection[0]]))->toBeTrue();
});
