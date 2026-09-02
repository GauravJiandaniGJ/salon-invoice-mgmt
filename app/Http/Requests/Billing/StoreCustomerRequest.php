<?php

namespace App\Http\Requests\Billing;

/** Same rules as update; the duplicate-phone check has no record to exclude. */
class StoreCustomerRequest extends UpdateCustomerRequest {}
