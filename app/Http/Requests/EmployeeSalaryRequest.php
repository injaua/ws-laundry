<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesAmounts;
use App\Models\EmployeeSalary;
use App\Support\Money;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * A payslip: salaries.store and salaries.update.
 *
 * One payslip per employee and month, and the deductions can never exceed what is
 * earned (base salary + bonus): the net salary is never negative. Paid payslips are
 * locked in the controller.
 */
class EmployeeSalaryRequest extends FormRequest
{
    use ValidatesAmounts;

    public function authorize(): bool
    {
        $salary = $this->salary();

        return $salary ? Gate::allows("update", $salary) : Gate::allows("create", EmployeeSalary::class);
    }

    public function rules(): array
    {
        return [
            "employee_id" => ["required", "integer", Rule::exists("employees", "id")],
            "period_year" => ["required", "integer", "min:2000", "max:2100"],
            "period_month" => ["required", "integer", "min:1", "max:12"],
            "base_salary" => $this->amountRules(),
            "bonus" => $this->amountRules(required: false),
            "deduction" => $this->amountRules(required: false),
            "absence_deduction" => $this->amountRules(required: false),
            "notes" => ["nullable", "string", "max:2000"],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                if ($this->periodTaken()) {
                    $validator->errors()->add("period_month", __("app.payslip_period_exists"));
                }

                $earned = Money::add($this->input("base_salary"), $this->input("bonus"));
                $deducted = Money::add($this->input("deduction"), $this->input("absence_deduction"));
                if (Money::compare($deducted, $earned) > 0) {
                    $validator->errors()->add("deduction", __("app.payslip_negative_net", ["amount" => money($earned)]));
                }
            },
        ];
    }

    /** The payslip being edited, null when creating one. */
    public function salary(): ?EmployeeSalary
    {
        $salary = $this->route("salary");

        return $salary instanceof EmployeeSalary ? $salary : null;
    }

    private function periodTaken(): bool
    {
        return EmployeeSalary::where("employee_id", $this->input("employee_id"))
            ->where("period_year", $this->input("period_year"))
            ->where("period_month", $this->input("period_month"))
            ->when($this->salary(), fn ($q, $salary) => $q->whereKeyNot($salary->id))
            ->exists();
    }
}
