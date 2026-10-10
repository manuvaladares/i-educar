<?php

namespace Tests\Api;

use App\Models\Employee;
use Database\Factories\EmployeeFactory;
use Database\Factories\LegacyIndividualFactory;
use Database\Factories\LegacyUserFactory;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class EmployeeWorkloadTest extends TestCase
{
    use DatabaseTransactions;

    private const ROUTE = '/intranet/educar_servidor_cad.php';

    public function test_employee_workload_can_be_updated_with_valid_value(): void
    {
        $employee = $this->employeeScenario();

        $this->post(self::ROUTE, $this->editPayload($employee, '40:30'))
            ->assertRedirectContains('educar_servidor_det.php');

        $this->assertDatabaseHas($employee->getTable(), [
            'cod_servidor' => $employee->getKey(),
            'ref_cod_instituicao' => $employee->ref_cod_instituicao,
            'carga_horaria' => 40.5,
            'ativo' => 1,
        ]);
    }

    private function employeeScenario(): Employee
    {
        $user = LegacyUserFactory::new()->admin()->create();
        $individual = LegacyIndividualFactory::new()->create();

        $this->actingAs($user);

        return EmployeeFactory::new()->create([
            'id' => $individual->getKey(),
            'institution_id' => $user->ref_cod_instituicao,
            'workload' => 20,
        ]);
    }

    private function editPayload(Employee $employee, string $workload): array
    {
        return [
            'tipoacao' => 'Editar',
            'cod_servidor' => $employee->getKey(),
            'ref_cod_instituicao' => $employee->ref_cod_instituicao,
            'ref_cod_instituicao_original' => $employee->ref_cod_instituicao,
            'ref_idesco' => $employee->ref_idesco,
            'carga_horaria' => $workload,
        ];
    }
}
