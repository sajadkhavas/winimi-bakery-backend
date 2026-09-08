<?php

namespace App\Http\Requests;

use App\Enums\DeliveryMethod;
use App\Services\Orders\CookieBulkDiscountService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class CheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('customer') !== null;
    }

    public function rules(): array
    {
        $usesSavedAddress = $this->filled('addressId');

        return [
            'addressId' => ['nullable', 'string', 'size:26'],
            'customer' => [
                Rule::requiredIf(! $usesSavedAddress),
                'nullable',
                'array',
            ],
            'customer.fullName' => [
                Rule::requiredIf(! $usesSavedAddress),
                'nullable',
                'string',
                'min:2',
                'max:120',
            ],
            'customer.mobile' => [
                Rule::requiredIf(! $usesSavedAddress),
                'nullable',
                'string',
                'max:32',
            ],
            'customer.province' => [
                Rule::requiredIf(! $usesSavedAddress),
                'nullable',
                'string',
                'max:100',
            ],
            'customer.city' => [
                Rule::requiredIf(! $usesSavedAddress),
                'nullable',
                'string',
                'max:100',
            ],
            'customer.address' => [
                Rule::requiredIf(! $usesSavedAddress),
                'nullable',
                'string',
                'max:1200',
            ],
            'customer.postalCode' => [
                'nullable',
                'string',
                'max:20',
            ],
            'customer.notes' => [
                'nullable',
                'string',
                'max:1000',
            ],
            'deliveryMethod' => [
                'required',
                Rule::in([
                    DeliveryMethod::Standard->value,
                ]),
            ],
            'items' => [
                'required',
                'array',
                'min:1',
                'max:30',
            ],
            'items.*' => [
                'required',
                'array',
            ],
            'items.*.variantId' => [
                'required',
                'string',
                'size:26',
            ],
            'items.*.quantity' => [
                'required',
                'integer',
                'min:1',
            ],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                // Shape/type errors take precedence; quantity policy only runs
                // when every item has already passed the structural rules.
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $items = $this->input('items', []);
                if (! is_array($items)) {
                    return;
                }

                $violations = app(CookieBulkDiscountService::class)
                    ->checkoutQuantityViolations($items);

                foreach ($violations as $message) {
                    $validator->errors()->add('items', $message);
                }
            },
        ];
    }
}
