<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Developer CRUD</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: monospace; padding: 20px; background: #f5f5f5; }
        .container { max-width: 1400px; margin: 0 auto; background: white; padding: 20px; }
        h1 { margin-bottom: 20px; }
        
        /* Tabs */
        .tabs { display: flex; gap: 5px; border-bottom: 2px solid #ddd; margin-bottom: 20px; flex-wrap: wrap; }
        .tab { padding: 10px 15px; cursor: pointer; border: 1px solid #ddd; border-bottom: none; background: #f5f5f5; }
        .tab.active { background: white; border-bottom: 2px solid white; margin-bottom: -2px; font-weight: bold; }
        
        /* Content */
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        
        /* Tables */
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 8px; text-align: left; border: 1px solid #ddd; }
        th { background: #f5f5f5; font-weight: bold; }
        tr:hover { background: #f9f9f9; }
        
        /* Forms */
        .form-section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; }
        .form-section h3 { margin-bottom: 15px; }
        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 10px; margin-bottom: 10px; }
        .form-group { display: flex; flex-direction: column; }
        label { font-weight: bold; margin-bottom: 3px; font-size: 12px; }
        input, select, textarea { padding: 6px; border: 1px solid #ddd; font-family: monospace; }
        button { padding: 8px 15px; cursor: pointer; border: 1px solid #333; background: #333; color: white; margin-right: 5px; }
        button:hover { background: #555; }
        
        /* Actions */
        .actions { margin-top: 10px; }
        .btn-update { background: #0066cc; border-color: #0066cc; }
        .btn-delete { background: #cc0000; border-color: #cc0000; }
        .btn-update:hover { background: #0052a3; }
        .btn-delete:hover { background: #a30000; }
        
        .message { padding: 10px; margin: 10px 0; border: 1px solid #ddd; }
        .message.success { background: #d4edda; border-color: #c3e6cb; }
        .message.error { background: #f8d7da; border-color: #f5c6cb; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Developer CRUD Interface</h1>
        
        <div class="tabs">
            <div class="tab active" onclick="switchTab('addresses')">Addresses</div>
            <div class="tab" onclick="switchTab('contacts')">Contacts</div>
            <div class="tab" onclick="switchTab('personal_data')">Personal Data</div>
            <div class="tab" onclick="switchTab('users')">Users</div>
            <div class="tab" onclick="switchTab('accounts')">Accounts</div>
            <div class="tab" onclick="switchTab('loans')">Loans</div>
            <div class="tab" onclick="switchTab('deposits')">Deposits</div>
            <div class="tab" onclick="switchTab('withdrawals')">Withdrawals</div>
            <div class="tab" onclick="switchTab('loan_payments')">Loan Payments</div>
        </div>

        <!-- ADDRESSES -->
        <div id="addresses" class="tab-content active">
            <h2>Addresses</h2>
            
            <div class="form-section">
                <h3>Create New Address</h3>
                <form action="/crud/addresses/create" method="POST">
                    @csrf
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Address ID *</label>
                            <input type="text" name="address_id" maxlength="16" required>
                        </div>
                        <div class="form-group">
                            <label>Country</label>
                            <input type="text" name="country" maxlength="100">
                        </div>
                        <div class="form-group">
                            <label>Region</label>
                            <input type="text" name="region" maxlength="100">
                        </div>
                        <div class="form-group">
                            <label>Province</label>
                            <input type="text" name="province" maxlength="100">
                        </div>
                        <div class="form-group">
                            <label>City/Municipality</label>
                            <input type="text" name="city_municipality" maxlength="100">
                        </div>
                        <div class="form-group">
                            <label>Barangay</label>
                            <input type="text" name="barangay" maxlength="100">
                        </div>
                        <div class="form-group">
                            <label>Street</label>
                            <input type="text" name="street" maxlength="255">
                        </div>
                        <div class="form-group">
                            <label>Zip Code</label>
                            <input type="text" name="zip_code" maxlength="20">
                        </div>
                    </div>
                    <button type="submit">Create Address</button>
                </form>
            </div>

            <div class="form-section">
                <h3>All Addresses</h3>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Country</th>
                            <th>Region</th>
                            <th>Province</th>
                            <th>City</th>
                            <th>Barangay</th>
                            <th>Street</th>
                            <th>Zip</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($addresses ?? [] as $address)
                        <tr>
                            <td>{{ $address->address_id }}</td>
                            <td>{{ $address->country }}</td>
                            <td>{{ $address->region }}</td>
                            <td>{{ $address->province }}</td>
                            <td>{{ $address->city_municipality }}</td>
                            <td>{{ $address->barangay }}</td>
                            <td>{{ $address->street }}</td>
                            <td>{{ $address->zip_code }}</td>
                            <td>
                                <form action="/crud/addresses/update" method="POST" style="display:inline;">
                                    @csrf
                                    <input type="hidden" name="address_id" value="{{ $address->address_id }}">
                                    <button type="submit" class="btn-update">Edit</button>
                                </form>
                                <form action="/crud/addresses/delete" method="POST" style="display:inline;">
                                    @csrf
                                    <input type="hidden" name="address_id" value="{{ $address->address_id }}">
                                    <button type="submit" class="btn-delete" onclick="return confirm('Delete this address?')">Delete</button>
                                </form>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="9">No addresses found</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- CONTACTS -->
        <div id="contacts" class="tab-content">
            <h2>Contacts</h2>
            
            <div class="form-section">
                <h3>Create New Contact</h3>
                <form action="/crud/contacts/create" method="POST">
                    @csrf
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Contact ID *</label>
                            <input type="text" name="contact_id" maxlength="16" required>
                        </div>
                        <div class="form-group">
                            <label>Phone Number</label>
                            <input type="text" name="phone_no" maxlength="11">
                        </div>
                        <div class="form-group">
                            <label>Phone Verified At</label>
                            <input type="datetime-local" name="phone_verified_at">
                        </div>
                        <div class="form-group">
                            <label>Email</label>
                            <input type="email" name="email" maxlength="255">
                        </div>
                        <div class="form-group">
                            <label>Email Verified At</label>
                            <input type="datetime-local" name="email_verified_at">
                        </div>
                    </div>
                    <button type="submit">Create Contact</button>
                </form>
            </div>

            <div class="form-section">
                <h3>All Contacts</h3>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Phone</th>
                            <th>Phone Verified</th>
                            <th>Email</th>
                            <th>Email Verified</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($contacts ?? [] as $contact)
                        <tr>
                            <td>{{ $contact->contact_id }}</td>
                            <td>{{ $contact->phone_no }}</td>
                            <td>{{ $contact->phone_verified_at }}</td>
                            <td>{{ $contact->email }}</td>
                            <td>{{ $contact->email_verified_at }}</td>
                            <td>
                                <form action="/crud/contacts/update" method="POST" style="display:inline;">
                                    @csrf
                                    <input type="hidden" name="contact_id" value="{{ $contact->contact_id }}">
                                    <button type="submit" class="btn-update">Edit</button>
                                </form>
                                <form action="/crud/contacts/delete" method="POST" style="display:inline;">
                                    @csrf
                                    <input type="hidden" name="contact_id" value="{{ $contact->contact_id }}">
                                    <button type="submit" class="btn-delete" onclick="return confirm('Delete this contact?')">Delete</button>
                                </form>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="6">No contacts found</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- PERSONAL DATA -->
        <div id="personal_data" class="tab-content">
            <h2>Personal Data</h2>
            
            <div class="form-section">
                <h3>Create New Personal Data</h3>
                <form action="/crud/personal_data/create" method="POST">
                    @csrf
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Personal Data ID *</label>
                            <input type="text" name="personal_data_id" maxlength="16" required>
                        </div>
                        <div class="form-group">
                            <label>First Name *</label>
                            <input type="text" name="first_name" maxlength="100" required>
                        </div>
                        <div class="form-group">
                            <label>Last Name *</label>
                            <input type="text" name="last_name" maxlength="100" required>
                        </div>
                        <div class="form-group">
                            <label>Middle Name</label>
                            <input type="text" name="mid_name" maxlength="100">
                        </div>
                        <div class="form-group">
                            <label>Suffix</label>
                            <input type="text" name="suffix" maxlength="50">
                        </div>
                        <div class="form-group">
                            <label>Birth Date</label>
                            <input type="date" name="birth_date">
                        </div>
                        <div class="form-group">
                            <label>Address ID</label>
                            <input type="text" name="address_id" maxlength="16">
                        </div>
                        <div class="form-group">
                            <label>Contact ID</label>
                            <input type="text" name="contact_id" maxlength="16">
                        </div>
                    </div>
                    <button type="submit">Create Personal Data</button>
                </form>
            </div>

            <div class="form-section">
                <h3>All Personal Data</h3>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>First Name</th>
                            <th>Last Name</th>
                            <th>Middle Name</th>
                            <th>Suffix</th>
                            <th>Birth Date</th>
                            <th>Address ID</th>
                            <th>Contact ID</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($personal_data ?? [] as $pd)
                        <tr>
                            <td>{{ $pd->personal_data_id }}</td>
                            <td>{{ $pd->first_name }}</td>
                            <td>{{ $pd->last_name }}</td>
                            <td>{{ $pd->mid_name }}</td>
                            <td>{{ $pd->suffix }}</td>
                            <td>{{ $pd->birth_date }}</td>
                            <td>{{ $pd->address_id }}</td>
                            <td>{{ $pd->contact_id }}</td>
                            <td>
                                <form action="/crud/personal_data/update" method="POST" style="display:inline;">
                                    @csrf
                                    <input type="hidden" name="personal_data_id" value="{{ $pd->personal_data_id }}">
                                    <button type="submit" class="btn-update">Edit</button>
                                </form>
                                <form action="/crud/personal_data/delete" method="POST" style="display:inline;">
                                    @csrf
                                    <input type="hidden" name="personal_data_id" value="{{ $pd->personal_data_id }}">
                                    <button type="submit" class="btn-delete" onclick="return confirm('Delete this personal data?')">Delete</button>
                                </form>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="9">No personal data found</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- USERS -->
        <div id="users" class="tab-content">
            <h2>Users</h2>
            
            <div class="form-section">
                <h3>Create New User</h3>
                <form action="/crud/users/create" method="POST">
                    @csrf
                    <div class="form-grid">
                        <div class="form-group">
                            <label>User ID *</label>
                            <input type="text" name="user_id" maxlength="16" required>
                        </div>
                        <div class="form-group">
                            <label>Personal Data ID</label>
                            <input type="text" name="personal_data_id" maxlength="16">
                        </div>
                        <div class="form-group">
                            <label>Email *</label>
                            <input type="email" name="email" required>
                        </div>
                        <div class="form-group">
                            <label>Password *</label>
                            <input type="password" name="password" required>
                        </div>
                        <div class="form-group">
                            <label>Role *</label>
                            <select name="role" required>
                                <option value="developer">Developer</option>
                                <option value="admin">Admin</option>
                                <option value="member">Member</option>
                                <option value="officer">Officer</option>
                                <option value="inactive" selected>Inactive</option>
                                <option value="terminated">Terminated</option>
                            </select>
                        </div>
                    </div>
                    <button type="submit">Create User</button>
                </form>
            </div>

            <div class="form-section">
                <h3>All Users</h3>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Personal Data ID</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Created At</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($users ?? [] as $user)
                        <tr>
                            <td>{{ $user->user_id }}</td>
                            <td>{{ $user->personal_data_id }}</td>
                            <td>{{ $user->email }}</td>
                            <td>{{ $user->role }}</td>
                            <td>{{ $user->created_at }}</td>
                            <td>
                                <form action="/crud/users/update" method="POST" style="display:inline;">
                                    @csrf
                                    <input type="hidden" name="user_id" value="{{ $user->user_id }}">
                                    <button type="submit" class="btn-update">Edit</button>
                                </form>
                                <form action="/crud/users/delete" method="POST" style="display:inline;">
                                    @csrf
                                    <input type="hidden" name="user_id" value="{{ $user->user_id }}">
                                    <button type="submit" class="btn-delete" onclick="return confirm('Delete this user?')">Delete</button>
                                </form>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="6">No users found</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ACCOUNTS -->
        <div id="accounts" class="tab-content">
            <h2>Accounts</h2>
            
            <div class="form-section">
                <h3>Create New Account</h3>
                <form action="/crud/accounts/create" method="POST">
                    @csrf
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Account ID *</label>
                            <input type="text" name="account_id" maxlength="16" required>
                        </div>
                        <div class="form-group">
                            <label>User ID</label>
                            <input type="text" name="user_id" maxlength="16">
                        </div>
                        <div class="form-group">
                            <label>Account Type *</label>
                            <select name="account_type" required>
                                <option value="savings" selected>Savings</option>
                                <option value="checking">Checking</option>
                                <option value="loan">Loan</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Balance</label>
                            <input type="number" name="balance" step="0.01" value="0">
                        </div>
                    </div>
                    <button type="submit">Create Account</button>
                </form>
            </div>

            <div class="form-section">
                <h3>All Accounts</h3>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>User ID</th>
                            <th>Type</th>
                            <th>Balance</th>
                            <th>Created At</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($accounts ?? [] as $account)
                        <tr>
                            <td>{{ $account->account_id }}</td>
                            <td>{{ $account->user_id }}</td>
                            <td>{{ $account->account_type }}</td>
                            <td>{{ number_format($account->balance, 2) }}</td>
                            <td>{{ $account->created_at }}</td>
                            <td>
                                <form action="/crud/accounts/update" method="POST" style="display:inline;">
                                    @csrf
                                    <input type="hidden" name="account_id" value="{{ $account->account_id }}">
                                    <button type="submit" class="btn-update">Edit</button>
                                </form>
                                <form action="/crud/accounts/delete" method="POST" style="display:inline;">
                                    @csrf
                                    <input type="hidden" name="account_id" value="{{ $account->account_id }}">
                                    <button type="submit" class="btn-delete" onclick="return confirm('Delete this account?')">Delete</button>
                                </form>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="6">No accounts found</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- LOANS -->
        <div id="loans" class="tab-content">
            <h2>Loans</h2>
            
            <div class="form-section">
                <h3>Create New Loan</h3>
                <form action="/crud/loans/create" method="POST">
                    @csrf
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Loan ID *</label>
                            <input type="text" name="loan_id" maxlength="16" required>
                        </div>
                        <div class="form-group">
                            <label>Account ID</label>
                            <input type="text" name="account_id" maxlength="16">
                        </div>
                        <div class="form-group">
                            <label>Loan Type *</label>
                            <input type="text" name="loan_type" maxlength="50" required>
                        </div>
                        <div class="form-group">
                            <label>Loan Amount *</label>
                            <input type="number" name="loan_amount" step="0.01" required>
                        </div>
                        <div class="form-group">
                            <label>Interest Rate *</label>
                            <input type="number" name="interest_rate" step="0.01" required>
                        </div>
                        <div class="form-group">
                            <label>Payment Frequency *</label>
                            <select name="payment_frequency" required>
                                <option value="weekly">Weekly</option>
                                <option value="monthly" selected>Monthly</option>
                                <option value="quarterly">Quarterly</option>
                                <option value="annually">Annually</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Loan Status *</label>
                            <select name="loan_status" required>
                                <option value="active" selected>Active</option>
                                <option value="paid_off">Paid Off</option>
                                <option value="defaulted">Defaulted</option>
                                <option value="closed">Closed</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Approved At</label>
                            <input type="datetime-local" name="approved_at">
                        </div>
                        <div class="form-group">
                            <label>Approved By</label>
                            <input type="text" name="approved_by" maxlength="16">
                        </div>
                        <div class="form-group">
                            <label>Due Date</label>
                            <input type="date" name="due_date">
                        </div>
                    </div>
                    <button type="submit">Create Loan</button>
                </form>
            </div>

            <div class="form-section">
                <h3>All Loans</h3>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Account ID</th>
                            <th>Type</th>
                            <th>Amount</th>
                            <th>Interest Rate</th>
                            <th>Frequency</th>
                            <th>Status</th>
                            <th>Due Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($loans ?? [] as $loan)
                        <tr>
                            <td>{{ $loan->loan_id }}</td>
                            <td>{{ $loan->account_id }}</td>
                            <td>{{ $loan->loan_type }}</td>
                            <td>{{ number_format($loan->loan_amount, 2) }}</td>
                            <td>{{ $loan->interest_rate }}%</td>
                            <td>{{ $loan->payment_frequency }}</td>
                            <td>{{ $loan->loan_status }}</td>
                            <td>{{ $loan->due_date }}</td>
                            <td>
                                <form action="/crud/loans/update" method="POST" style="display:inline;">
                                    @csrf
                                    <input type="hidden" name="loan_id" value="{{ $loan->loan_id }}">
                                    <button type="submit" class="btn-update">Edit</button>
                                </form>
                                <form action="/crud/loans/delete" method="POST" style="display:inline;">
                                    @csrf
                                    <input type="hidden" name="loan_id" value="{{ $loan->loan_id }}">
                                    <button type="submit" class="btn-delete" onclick="return confirm('Delete this loan?')">Delete</button>
                                </form>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="9">No loans found</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- DEPOSITS -->
        <div id="deposits" class="tab-content">
            <h2>Deposits</h2>
            
            <div class="form-section">
                <h3>Create New Deposit</h3>
                <form action="/crud/deposits/create" method="POST">
                    @csrf
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Deposit ID *</label>
                            <input type="text" name="deposit_id" maxlength="16" required>
                        </div>
                        <div class="form-group">
                            <label>Account ID</label>
                            <input type="text" name="account_id" maxlength="16">
                        </div>
                        <div class="form-group">
                            <label>Deposit Amount *</label>
                            <input type="number" name="deposit_amount" step="0.01" required>
                        </div>
                        <div class="form-group">
                            <label>Deposit Date</label>
                            <input type="datetime-local" name="deposit_date">
                        </div>
                        <div class="form-group">
                            <label>Recorded By</label>
                            <input type="text" name="recorded_by" maxlength="16">
                        </div>
                    </div>
                    <button type="submit">Create Deposit</button>
                </form>
            </div>

            <div class="form-section">
                <h3>All Deposits</h3>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Account ID</th>
                            <th>Amount</th>
                            <th>Deposit Date</th>
                            <th>Recorded By</th>
                            <th>Created At</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($deposits ?? [] as $deposit)
                        <tr>
                            <td>{{ $deposit->deposit_id }}</td>
                            <td>{{ $deposit->account_id }}</td>
                            <td>{{ number_format($deposit->deposit_amount, 2) }}</td>
                            <td>{{ $deposit->deposit_date }}</td>
                            <td>{{ $deposit->recorded_by }}</td>
                            <td>{{ $deposit->created_at }}</td>
                            <td>
                                <form action="/crud/deposits/update" method="POST" style="display:inline;">
                                    @csrf
                                    <input type="hidden" name="deposit_id" value="{{ $deposit->deposit_id }}">
                                    <button type="submit" class="btn-update">Edit</button>
                                </form>
                                <form action="/crud/deposits/delete" method="POST" style="display:inline;">
                                    @csrf
                                    <input type="hidden" name="deposit_id" value="{{ $deposit->deposit_id }}">
                                    <button type="submit" class="btn-delete" onclick="return confirm('Delete this deposit?')">Delete</button>
                                </form>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="7">No deposits found</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- WITHDRAWALS -->
        <div id="withdrawals" class="tab-content">
            <h2>Withdrawals</h2>
            
            <div class="form-section">
                <h3>Create New Withdrawal</h3>
                <form action="/crud/withdrawals/create" method="POST">
                    @csrf
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Withdrawal ID *</label>
                            <input type="text" name="withdrawal_id" maxlength="16" required>
                        </div>
                        <div class="form-group">
                            <label>Account ID</label>
                            <input type="text" name="account_id" maxlength="16">
                        </div>
                        <div class="form-group">
                            <label>Withdrawal Amount *</label>
                            <input type="number" name="withdrawal_amount" step="0.01" required>
                        </div>
                        <div class="form-group">
                            <label>Recorded By</label>
                            <input type="text" name="recorded_by" maxlength="16">
                        </div>
                    </div>
                    <button type="submit">Create Withdrawal</button>
                </form>
            </div>

            <div class="form-section">
                <h3>All Withdrawals</h3>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Account ID</th>
                            <th>Amount</th>
                            <th>Recorded By</th>
                            <th>Created At</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($withdrawals ?? [] as $withdrawal)
                        <tr>
                            <td>{{ $withdrawal->withdrawal_id }}</td>
                            <td>{{ $withdrawal->account_id }}</td>
                            <td>{{ number_format($withdrawal->withdrawal_amount, 2) }}</td>
                            <td>{{ $withdrawal->recorded_by }}</td>
                            <td>{{ $withdrawal->created_at }}</td>
                            <td>
                                <form action="/crud/withdrawals/update" method="POST" style="display:inline;">
                                    @csrf
                                    <input type="hidden" name="withdrawal_id" value="{{ $withdrawal->withdrawal_id }}">
                                    <button type="submit" class="btn-update">Edit</button>
                                </form>
                                <form action="/crud/withdrawals/delete" method="POST" style="display:inline;">
                                    @csrf
                                    <input type="hidden" name="withdrawal_id" value="{{ $withdrawal->withdrawal_id }}">
                                    <button type="submit" class="btn-delete" onclick="return confirm('Delete this withdrawal?')">Delete</button>
                                </form>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="6">No withdrawals found</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- LOAN PAYMENTS -->
        <div id="loan_payments" class="tab-content">
            <h2>Loan Payments</h2>
            
            <div class="form-section">
                <h3>Create New Loan Payment</h3>
                <form action="/crud/loan_payments/create" method="POST">
                    @csrf
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Payment ID *</label>
                            <input type="text" name="payment_id" maxlength="16" required>
                        </div>
                        <div class="form-group">
                            <label>Loan ID</label>
                            <input type="text" name="loan_id" maxlength="16">
                        </div>
                        <div class="form-group">
                            <label>Payment Amount *</label>
                            <input type="number" name="payment_amount" step="0.01" required>
                        </div>
                    </div>
                    <button type="submit">Create Loan Payment</button>
                </form>
            </div>

            <div class="form-section">
                <h3>All Loan Payments</h3>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Loan ID</th>
                            <th>Amount</th>
                            <th>Created At</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($loan_payments ?? [] as $payment)
                        <tr>
                            <td>{{ $payment->payment_id }}</td>
                            <td>{{ $payment->loan_id }}</td>
                            <td>{{ number_format($payment->payment_amount, 2) }}</td>
                            <td>{{ $payment->created_at }}</td>
                            <td>
                                <form action="/crud/loan_payments/update" method="POST" style="display:inline;">
                                    @csrf
                                    <input type="hidden" name="payment_id" value="{{ $payment->payment_id }}">
                                    <button type="submit" class="btn-update">Edit</button>
                                </form>
                                <form action="/crud/loan_payments/delete" method="POST" style="display:inline;">
                                    @csrf
                                    <input type="hidden" name="payment_id" value="{{ $payment->payment_id }}">
                                    <button type="submit" class="btn-delete" onclick="return confirm('Delete this loan payment?')">Delete</button>
                                </form>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="5">No loan payments found</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        function switchTab(tabName) {
            // Hide all tab contents
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.remove('active');
            });
            
            // Remove active class from all tabs
            document.querySelectorAll('.tab').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Show selected tab content
            document.getElementById(tabName).classList.add('active');
            
            // Add active class to clicked tab
            event.target.classList.add('active');
        }
    </script>
</body>
</html>
