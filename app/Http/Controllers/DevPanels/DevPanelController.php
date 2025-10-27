<?php

namespace App\Http\Controllers\DevPanels;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log as LaravelLog;
use Illuminate\Support\Str;
use App\Http\Controllers\Controller;

use App\Models\{
    Account,
    Address,
    Contact,
    Deposit,
    Loan,
    LoanPayment,
    PersonalData,
    User,
    Withdrawal,
    Log,
    Transaction
};

/**
 * Developer Panel Controller
 * Handles CRUD operations for all database tables
 * WARNING: This is for development/testing only - remove before production
 */
class DevPanelController extends Controller
{
    // List of all manageable tables
    private $tables = [
        'accounts',
        'addresses',
        'contacts',
        'deposits',
        'loan_payments',
        'loans',
        'personal_data',
        'users',
        'withdrawals',
        'transactions',
        'logs'
    ];

    // Default user ID for logging actions
    private $developerId = 'USR_0001';

    // Show the main developer panel
    public function index(Request $request)
    {
        $active = $request->get('tab', 'users_group');
        $table = $request->get('table', 'accounts');

        // Get users with related data (address, contact, personal info)
        $usersGroup = $this->getCombinedUsers();

        // Get data for the selected table
        $model = $this->getModel($table);
        $tableData = $model ? $model::limit(50)->get() : collect([]);
        $columns = $this->getTableColumns($table);

        return view('dev.developerPanel', compact('active', 'table', 'tableData', 'columns', 'usersGroup'));
    }

    // ============================================================
    // USER MANAGEMENT (with related tables: address, contact, personal data)
    // ============================================================

    // Create a new user with all related records in one transaction
    public function storeCombinedUser(Request $request)
    {
        LaravelLog::info('=== User Creation Started ===');
        LaravelLog::info('Request data: ', $request->all());

        try {
            $validated = $request->validate([
                'email' => 'required|email|max:255|unique:users,email',
                'password' => 'required|string|min:6',
                'first_name' => 'required|string|max:100',
                'last_name' => 'required|string|max:100',
                'role' => 'required|string'
            ]);
            LaravelLog::info('Validation passed');
        } catch (\Illuminate\Validation\ValidationException $e) {
            LaravelLog::error('Validation failed: ', $e->errors());
            return back()->withErrors($e->errors())->withInput()->with('error', 'Validation failed: ' . json_encode($e->errors()));
        }

        DB::beginTransaction();
        try {
            // Create records in order: address → contact → personal data → user
            LaravelLog::info('Creating Address...');
            $address = new Address($request->only([
                'country',
                'region',
                'province',
                'city_municipality',
                'barangay',
                'street',
                'zip_code'
            ]));
            $address->save();
            LaravelLog::info('Address created: ' . $address->address_id);

            LaravelLog::info('Creating Contact...');
            $contact = new Contact($request->only(['phone_no', 'email']));
            $contact->save();
            LaravelLog::info('Contact created: ' . $contact->contact_id);

            LaravelLog::info('Creating PersonalData...');
            $personal = new PersonalData($request->only(['first_name', 'last_name', 'mid_name', 'suffix', 'birth_date']));
            $personal->address_id = $address->address_id;
            $personal->contact_id = $contact->contact_id;
            $personal->save();
            LaravelLog::info('PersonalData created: ' . $personal->personal_data_id);

            LaravelLog::info('Creating User...');
            $user = new User();
            $user->personal_data_id = $personal->personal_data_id;
            $user->email = $request->email;
            $user->password = bcrypt($request->password);
            $user->role = $request->role;
            $user->save();
            LaravelLog::info('User created: ' . $user->user_id);

            $this->logActivity("Created new user {$user->user_id} ({$request->email}).");

            DB::commit();
            LaravelLog::info('=== User Creation Success ===');
            return back()->with('success', 'User and related records created successfully!');
        } catch (\Throwable $e) {
            DB::rollBack();
            LaravelLog::error('=== User Creation Failed ===');
            LaravelLog::error('Error: ' . $e->getMessage());
            LaravelLog::error('Stack trace: ' . $e->getTraceAsString());
            return back()->with('error', 'Creation failed: ' . $e->getMessage())->withInput();
        }
    }

    // Update user and all related records
    public function updateCombinedUser(Request $request)
    {
        DB::beginTransaction();
        try {
            // Fetch all related records
            $user = User::findOrFail($request->user_id);
            $personal = PersonalData::findOrFail($request->personal_data_id);

            // Find or create contact and address if they don't exist
            $contact = null;
            $address = null;

            // Handle address - create if doesn't exist or update if it does
            if ($request->address_id) {
                $address = Address::find($request->address_id);
            }

            // If address doesn't exist but we have address data, create it
            if (!$address && $personal) {
                $addressData = $request->only(['country', 'region', 'province', 'city_municipality', 'barangay', 'street', 'zip_code']);
                $hasAddressData = collect($addressData)->filter(fn($val) => $val !== '' && $val !== null)->isNotEmpty();

                if ($hasAddressData) {
                    $address = new Address();
                    $address->save(); // Save to generate ID
                    $personal->address_id = $address->address_id;
                }
            }

            // Update address if it exists
            if ($address) {
                $addressData = $request->only(['country', 'region', 'province', 'city_municipality', 'barangay', 'street', 'zip_code']);
                foreach ($addressData as $key => $value) {
                    $address->$key = ($value !== '' && $value !== null) ? $value : null;
                }
                $address->save();
            }

            // Handle contact - create if doesn't exist or update if it does
            if ($request->contact_id) {
                $contact = Contact::find($request->contact_id);
            }

            // If contact doesn't exist but we have contact data, create it
            if (!$contact && $personal) {
                $contactData = $request->only(['phone_no', 'email']);
                $hasContactData = collect($contactData)->filter(fn($val) => $val !== '' && $val !== null)->isNotEmpty();

                if ($hasContactData) {
                    $contact = new Contact();
                    $contact->save(); // Save to generate ID
                    $personal->contact_id = $contact->contact_id;
                }
            }

            // Update contact if it exists
            if ($contact) {
                $contactData = $request->only(['phone_no', 'email']);
                foreach ($contactData as $key => $value) {
                    if ($key === 'email' && ($value === '' || $value === null)) {
                        continue; // Don't clear email
                    }
                    $contact->$key = ($value !== '' && $value !== null) ? $value : null;
                }
                $contact->save();
            }

            // Update personal data
            $personalData = $request->only(['first_name', 'last_name', 'mid_name', 'suffix', 'birth_date']);
            foreach ($personalData as $key => $value) {
                if ($value !== '' && $value !== null) {
                    $personal->$key = $value;
                } else {
                    // Only nullable fields can be set to null
                    if (in_array($key, ['mid_name', 'suffix', 'birth_date'])) {
                        $personal->$key = null;
                    }
                }
            }
            $personal->save();

            // Update user
            $user->email = $request->email;
            if ($request->filled('password')) {
                $user->password = bcrypt($request->password);
            }
            $user->role = $request->role;
            $user->save();

            $this->logActivity("Updated user {$user->user_id} ({$user->email}).");

            DB::commit();
            return back()->with('success', 'User updated successfully.');
        } catch (\Throwable $e) {
            DB::rollBack();
            return back()->with('error', 'Update failed: ' . $e->getMessage());
        }
    }

    // Delete user and clean up orphaned related records
    public function deleteCombinedUser(Request $request)
    {
        DB::beginTransaction();
        try {
            $user = User::findOrFail($request->user_id);
            $personal = PersonalData::findOrFail($request->personal_data_id);
            $contact = Contact::find($personal->contact_id);
            $address = Address::find($personal->address_id);

            // Delete user and personal data
            $user->delete();
            $personal->delete();

            // Only delete contact/address if no other records reference them
            if ($contact && !PersonalData::where('contact_id', $contact->contact_id)->exists()) {
                $contact->delete();
            }
            if ($address && !PersonalData::where('address_id', $address->address_id)->exists()) {
                $address->delete();
            }

            $this->logActivity("Deleted user {$request->user_id} and related records.");

            DB::commit();
            return back()->with('success', 'User deleted successfully.');
        } catch (\Throwable $e) {
            DB::rollBack();
            return back()->with('error', 'Delete failed: ' . $e->getMessage());
        }
    }

    // ============================================================
    // GENERIC TABLE CRUD (for all other tables)
    // ============================================================

    // Create a new record in any table
    public function store(Request $request)
    {
        $table = $request->input('table');
        $modelClass = $this->getModel($table);
        if (!$modelClass) return back()->with('error', 'Invalid table.');

        $data = $request->except(['_token', 'table']);

        DB::beginTransaction();
        try {
            $model = new $modelClass();

            // Check if foreign keys are valid
            $fkError = $this->checkForeignKeys($table, $data);
            if ($fkError) {
                DB::rollBack();
                return back()->with('error', $fkError);
            }

            // Set all fields (skip empty values to allow defaults/nulls)
            foreach ($data as $col => $val) {
                // Only set non-empty values, or explicitly set if value is 0
                if ($val !== '' && $val !== null) {
                    $model->$col = $val;
                }
            }
            $model->save();

            $this->logActivity("Created new record in {$table} ({$model->{$model->getKeyName()}}).");

            // Log financial transactions (deposits, withdrawals, loan payments)
            $this->logTransactionIfMoneyTable($table, $model, 'create');

            DB::commit();
            return back()->with('success', "Record created in {$table}.");
        } catch (\Throwable $e) {
            DB::rollBack();
            return back()->with('error', 'Insert failed: ' . $e->getMessage());
        }
    }

    // Update an existing record in any table
    public function update(Request $request)
    {
        $table = $request->input('table');
        $modelClass = $this->getModel($table);
        if (!$modelClass) return back()->with('error', 'Invalid table.');

        $pk = $this->getPrimaryKey($table);
        $id = $request->input($pk);
        $data = $request->except(['_token', 'table', $pk]);

        try {
            $model = $modelClass::findOrFail($id);

            // Validate foreign keys before updating
            $fkError = $this->checkForeignKeys($table, $data);
            if ($fkError) return back()->with('error', $fkError);

            $model->update($data);

            $this->logActivity("Updated {$table} record {$id}.");
            $this->logTransactionIfMoneyTable($table, $model, 'update');

            return back()->with('success', "Record updated in {$table}.");
        } catch (\Throwable $e) {
            return back()->with('error', 'Update failed: ' . $e->getMessage());
        }
    }

    // Delete a record from any table
    public function destroy(Request $request)
    {
        $table = $request->input('table');
        $modelClass = $this->getModel($table);
        if (!$modelClass) return back()->with('error', 'Invalid table.');

        $pk = $this->getPrimaryKey($table);
        $id = $request->input($pk);

        try {
            $model = $modelClass::findOrFail($id);
            $model->delete();

            $this->logActivity("Deleted {$table} record {$id}.");
            $this->logTransactionIfMoneyTable($table, $model, 'delete');

            return back()->with('success', "Record deleted from {$table}.");
        } catch (\Throwable $e) {
            return back()->with('error', 'Delete failed: ' . $e->getMessage());
        }
    }

    // ============================================================
    // HELPER METHODS
    // ============================================================

    // Map table name to its model class
    private function getModel($table)
    {
        return match ($table) {
            'accounts' => Account::class,
            'addresses' => Address::class,
            'contacts' => Contact::class,
            'deposits' => Deposit::class,
            'loan_payments' => LoanPayment::class,
            'loans' => Loan::class,
            'personal_data' => PersonalData::class,
            'users' => User::class,
            'withdrawals' => Withdrawal::class,
            'transactions' => Transaction::class,
            'logs' => Log::class,
            default => null,
        };
    }

    // Get the primary key column name for a table
    private function getPrimaryKey($table)
    {
        return match ($table) {
            'accounts' => 'account_id',
            'addresses' => 'address_id',
            'contacts' => 'contact_id',
            'deposits' => 'deposit_id',
            'loan_payments' => 'payment_id',
            'loans' => 'loan_id',
            'personal_data' => 'personal_data_id',
            'users' => 'user_id',
            'withdrawals' => 'withdrawal_id',
            'transactions' => 'transaction_id',
            'logs' => 'log_id',
            default => 'id',
        };
    }

    // Get all column names from a table
    private function getTableColumns($table)
    {
        $modelClass = $this->getModel($table);
        if (!$modelClass) return [];

        try {
            $tableName = (new $modelClass())->getTable();
            $columns = DB::getSchemaBuilder()->getColumnListing($tableName);
            return $columns;
        } catch (\Exception $e) {
            return [];
        }
    }

    // Fetch users with their related data (address, contact, personal info) for the combined view
    private function getCombinedUsers()
    {
        return User::join('personal_data', 'users.personal_data_id', '=', 'personal_data.personal_data_id')
            ->leftJoin('contacts', 'personal_data.contact_id', '=', 'contacts.contact_id')
            ->leftJoin('addresses', 'personal_data.address_id', '=', 'addresses.address_id')
            ->select(
                'users.user_id',
                'users.email',
                'users.role',
                'personal_data.personal_data_id',
                'personal_data.first_name',
                'personal_data.last_name',
                'contacts.contact_id',
                'contacts.phone_no',
                'addresses.address_id',
                'addresses.country',
                'addresses.city_municipality',
                'addresses.street'
            )->limit(50)->get();
    }

    // Check if foreign key references exist before saving
    private function checkForeignKeys($table, $data)
    {
        // Basic foreign key validation to prevent errors
        // Only validate if the field is provided and not empty
        if ($table === 'accounts' && isset($data['user_id']) && $data['user_id'] !== '' && !User::where('user_id', $data['user_id'])->exists()) {
            return 'Invalid user_id.';
        }

        if (in_array($table, ['deposits', 'withdrawals']) && isset($data['account_id']) && $data['account_id'] !== '' && !Account::where('account_id', $data['account_id'])->exists()) {
            return 'Invalid account_id.';
        }

        if ($table === 'loan_payments' && isset($data['loan_id']) && $data['loan_id'] !== '' && !Loan::where('loan_id', $data['loan_id'])->exists()) {
            return 'Invalid loan_id.';
        }

        return null;
    }

    // Save an activity log entry
    private function logActivity(string $message)
    {
        Log::create([
            'user_id' => $this->developerId,
            'activity' => $message,
        ]);
    }

    // Log financial transactions (deposits, withdrawals, loan payments) to the transactions table
    private function logTransactionIfMoneyTable($table, $model, $action)
    {
        // Only log for money-related tables
        if (!in_array($table, ['deposits', 'withdrawals', 'loan_payments'])) {
            return;
        }

        $type = match ($table) {
            'deposits' => 'deposit',
            'withdrawals' => 'withdrawal',
            'loan_payments' => 'loan_payment',
            default => 'transaction',
        };

        $amount = $model->deposit_amount ?? $model->withdrawal_amount ?? $model->payment_amount ?? 0;

        Transaction::create([
            'account_id' => $model->account_id ?? null,
            'processed_by' => $this->developerId,
            'transaction_type' => $type,
            'amount' => $amount,
        ]);
    }
}
