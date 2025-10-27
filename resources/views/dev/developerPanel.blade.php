<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title>Dev Panel</title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <style>
        body {
            font-family: system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial;
            margin: 24px;
            background: #fafafa;
            color: #111
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }

        h1 {
            font-size: 22px;
            margin: 0 0 12px;
            font-weight: 600;
        }

        h3 {
            font-size: 16px;
            margin: 0 0 8px;
            font-weight: 600;
            color: #111;
        }

        h4 {
            font-size: 14px;
            margin: 12px 0 8px;
            font-weight: 600;
            color: #374151;
        }

        hr {
            border: 0;
            border-top: 1px solid #e5e7eb;
            margin: 16px 0;
        }

        .card {
            background: #fff;
            padding: 24px;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            border: 1px solid #e5e7eb;
        }

        .card h3 {
            margin-top: 0;
        }

        .col {
            flex: 1
        }

        label {
            display: block;
            font-size: 13px;
            margin-top: 8px;
            color: #444;
            font-weight: 500;
        }

        input[type=text],
        input[type=email],
        input[type=password],
        select {
            width: 100%;
            max-width: 400px;
            padding: 8px 10px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            margin-top: 4px;
            font-size: 14px;
            transition: border-color 0.15s;
        }

        input[type=text]:focus,
        input[type=email]:focus,
        input[type=password]:focus,
        select:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        input[type=text]:disabled {
            background: #f3f4f6;
            cursor: not-allowed;
        }

        /* Inline inputs in tables */
        td input[type=text],
        td input[type=email],
        td input[type=password] {
            max-width: 180px;
            padding: 6px 8px;
            font-size: 13px;
        }

        td select {
            max-width: 140px;
            padding: 6px 8px;
            font-size: 13px;
        }

        button {
            padding: 8px 16px;
            border-radius: 6px;
            border: 0;
            background: #111;
            color: #fff;
            cursor: pointer;
            font-size: 13px;
            font-weight: 500;
            transition: background 0.15s;
            white-space: nowrap;
        }

        button:hover {
            background: #333;
        }

        button[type=submit]:last-child {
            background: #dc2626;
        }

        button[type=submit]:last-child:hover {
            background: #b91c1c;
        }

        .form-section {
            margin-bottom: 24px;
        }

        .form-section:last-child {
            margin-bottom: 0;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 12px;
            font-size: 13px;
        }

        th,
        td {
            padding: 12px 10px;
            border-bottom: 1px solid #e5e7eb;
            text-align: left;
            font-size: 13px;
            vertical-align: middle;
        }

        th:first-child,
        td:first-child {
            padding-left: 0;
        }

        th:last-child,
        td:last-child {
            padding-right: 0;
        }

        th {
            background: #f9fafb;
            font-weight: 600;
            color: #374151;
        }

        tr:hover {
            background: #f9fafb;
        }

        .small {
            font-size: 12px;
            color: #666
        }

        .muted {
            color: #777
        }

        .success-toast {
            background: #e6ffed;
            color: #055;
            padding: 10px;
            border-radius: 6px;
            margin: 10px 0
        }

        .error-toast {
            background: #ffecec;
            color: #5a0000;
            padding: 10px;
            border-radius: 6px;
            margin: 10px 0
        }

        .actions {
            display: flex;
            gap: 8px;
            align-items: center;
            justify-content: flex-start;
        }

        .inline-form {
            display: flex;
            gap: 8px;
            align-items: center
        }

        .hidden-pk {
            background: #f4f4f4;
            border-radius: 4px;
            padding: 6px;
            font-size: 12px;
            color: #333
        }

        .section-title {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 20px;
            padding-bottom: 16px;
            border-bottom: 2px solid #e5e7eb;
        }

        .section-title h1 {
            margin: 0;
        }

        .section-title p {
            color: #dc2626;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin: 0;
        }

        .note {
            font-size: 12px;
            color: #666;
            margin-top: 6px;
            line-height: 1.5;
        }

        fieldset {
            border: 0;
            padding: 0;
            margin: 0;
        }

        @media (max-width: 768px) {
            body {
                margin: 12px;
            }

            .container {
                padding: 0 12px;
            }

            .card {
                padding: 16px;
            }

            input[type=text],
            input[type=email],
            input[type=password],
            select {
                max-width: 100%;
            }
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="section-title">
            <div>
                <h1>Developer Panel</h1>
                <p style="margin: 0;">ONLY FOR TESTING PURPOSES</p>
            </div>
            <div class="small muted">Logged in as: {{ auth()->user()->personalData->first_name }}
                {{ auth()->user()->personalData->last_name }} ({{ auth()->user()->role }})</div>
        </div>
        {{-- Notifications --}}
        @if (session('success'))
            <div class="success-toast">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="error-toast">{{ session('error') }}</div>
        @endif
        @if ($errors->any())
            <div class="error-toast">
                <strong>Validation Errors:</strong>
                <ul style="margin: 8px 0 0 0; padding-left: 20px;">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="content-wrapper">
            <div class="col card">
                <h3>Database Tables</h3>

                <div class="form-section" style="margin-top: 16px;">
                    <form method="GET" action="{{ route('dev.panel') }}">
                        <label>Select Table:
                            <select name="table" onchange="this.form.submit()" style="max-width: 300px;">
                                @foreach (['accounts', 'deposits', 'loans', 'loan_payments', 'withdrawals', 'transactions', 'logs'] as $tbl)
                                    <option value="{{ $tbl }}" {{ ($table ?? '') === $tbl ? 'selected' : '' }}>
                                        {{ ucfirst(str_replace('_', ' ', $tbl)) }}</option>
                                @endforeach
                            </select>
                        </label>
                    </form>
                </div>

                <hr>

                {{-- Form section for creating new records in the selected table --}}
                <div class="form-section">
                    <h4>Create new ({{ $table ?? 'table' }})</h4>
                    <form method="POST" action="{{ route('dev.store') }}">
                        @csrf
                        <input type="hidden" name="table" value="{{ $table ?? '' }}">
                        <div style="display:flex;flex-direction:column;gap:10px;margin-top:12px;">
                            {{-- Generate input fields for each column except primary keys --}}
                            @foreach ($columns ?? [] as $col)
                                {{-- Check if column is primary key and skip if true --}}
                                @if (in_array($col, [$columns[0] ?? '']) && Str::contains($col, ['id', '_id']))
                                    @continue
                                @endif
                                {{-- Skip timestamp fields (Laravel handles these automatically) --}}
                                @if (in_array($col, ['created_at', 'updated_at']))
                                    @continue
                                @endif
                                {{-- - Create  --}}
                                <label>
                                    <h5>{{ $col }}</h5>

                                    <input type="text" name="{{ $col }}" style="max-width: 350px;">
                                </label>
                            @endforeach
                        </div>
                        <div style="margin-top:16px;"><button type="submit">Add Record</button></div>
                    </form>
                </div>

                <hr>

                <div class="form-section">
                    <h4>Existing rows ({{ $table ?? '' }})</h4>
                    @if (count($tableData ?? []) > 0)
                        <div style="overflow-x: auto; margin-top: 12px;">
                            <table>
                                <thead>
                                    <tr>
                                        @foreach ($columns ?? [] as $col)
                                            <th>{{ $col }}</th>
                                        @endforeach
                                        <th style="width: 180px;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($tableData ?? [] as $row)
                                        <tr>
                                            <form method="POST" action="{{ route('dev.update') }}">
                                                @csrf
                                                <input type="hidden" name="table" value="{{ $table ?? '' }}">
                                                @foreach ($columns ?? [] as $i => $col)
                                                    <td>
                                                        @if ($i === 0)
                                                            <div class="hidden-pk">{{ $row->$col }}</div>
                                                            <input type="hidden" name="{{ $col }}"
                                                                value="{{ $row->$col }}">
                                                        @else
                                                            <input type="text" name="{{ $col }}"
                                                                value="{{ $row->$col ?? '' }}"
                                                                style="width: 100%; max-width: 200px;">
                                                        @endif
                                                    </td>
                                                @endforeach
                                                <td class="actions">
                                                    <button type="submit">Save</button>
                                            </form>

                                            <form method="POST" action="{{ route('dev.delete') }}"
                                                style="display:inline">
                                                @csrf
                                                <input type="hidden" name="table" value="{{ $table ?? '' }}">
                                                <input type="hidden" name="{{ $columns[0] ?? 'id' }}"
                                                    value="{{ $row->{$columns[0] ?? 'id'} }}">
                                                <button type="submit"
                                                    onclick="return confirm('Delete row?')">Delete</button>
                                            </form>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <p class="note" style="margin-top:10px;">No records found</p>
                    @endif
                </div>

            </div>

            {{-- Combined Users group (shown second) --}}
            <div class="col card">
                <h3>Users (create / edit / delete)</h3>


                <div class="form-section" style="margin-top: 16px;">
                    <h4>Create New User</h4>
                    <form method="POST" action="{{ route('dev.users.combined.store') }}">
                        @csrf
                        <fieldset style="border:0;padding:0;margin-top:12px;">
                            <h4>Address</h4>
                            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
                                <label>Country<input type="text" name="country"></label>
                                <label>Region<input type="text" name="region"></label>
                                <label>Province<input type="text" name="province"></label>
                                <label>City/Municipality<input type="text" name="city_municipality"></label>
                                <label>Barangay<input type="text" name="barangay"></label>
                                <label>Street<input type="text" name="street"></label>
                                <label>Zip Code<input type="text" name="zip_code"></label>
                            </div>

                            <h4>Contact</h4>
                            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
                                <label>Phone<input type="text" name="phone_no"></label>
                                <label>Email<input type="email" name="email" required></label>
                            </div>

                            <h4>Personal</h4>
                            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
                                <label>First Name<input type="text" name="first_name" required></label>
                                <label>Last Name<input type="text" name="last_name" required></label>
                                <label>Middle Name<input type="text" name="mid_name"></label>
                                <label>Suffix<input type="text" name="suffix"></label>
                                <label>Birth Date<input type="text" name="birth_date"
                                        placeholder="YYYY-MM-DD"></label>
                            </div>

                            <h4>User</h4>
                            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
                                <label>Password<input type="password" name="password" required></label>
                                <label>Role
                                    <select name="role">
                                        <option value="developer">developer</option>
                                        <option value="admin">admin</option>
                                        <option value="member">member</option>
                                        <option value="officer">officer</option>
                                        <option value="inactive">inactive</option>
                                        <option value="terminated">terminated</option>
                                    </select>
                                </label>
                            </div>

                            <div style="margin-top:16px"><button type="submit">Create User</button>
                            </div>
                        </fieldset>
                    </form>
                </div>

                <hr>

                <div class="form-section">
                    <h4>Existing Users</h4>
                    <div class="note">Edit fields inline and click Save. Primary keys are read-only.</div>

                    <div style="overflow-x: auto; margin-top: 12px;">
                        <table>
                            <thead>
                                <tr>
                                    <th>User ID</th>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Phone</th>
                                    <th>Address</th>
                                    <th>Role</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($usersGroup as $u)
                                    <tr>
                                        <form method="POST" action="{{ route('dev.users.combined.update') }}">
                                            @csrf
                                            <td>
                                                <div class="hidden-pk">{{ $u->user_id }}</div>
                                                <input type="hidden" name="user_id" value="{{ $u->user_id }}">
                                                <input type="hidden" name="personal_data_id"
                                                    value="{{ $u->personal_data_id }}">
                                                <input type="hidden" name="contact_id"
                                                    value="{{ $u->contact_id }}">
                                                <input type="hidden" name="address_id"
                                                    value="{{ $u->address_id }}">
                                            </td>
                                            <td>
                                                <input type="text" name="first_name" value="{{ $u->first_name }}"
                                                    placeholder="first" style="width:100%; max-width:150px;">
                                                <input type="text" name="last_name" value="{{ $u->last_name }}"
                                                    placeholder="last"
                                                    style="width:100%; max-width:150px; margin-top:6px">
                                            </td>
                                            <td><input type="email" name="email" value="{{ $u->email }}"
                                                    style="max-width:200px;"></td>
                                            <td><input type="text" name="phone_no" value="{{ $u->phone_no }}"
                                                    style="max-width:150px;"></td>
                                            <td style="min-width:180px">
                                                <input type="text" name="country" value="{{ $u->country }}"
                                                    placeholder="country" style="width:100%; max-width:160px;">
                                                <input type="text" name="city_municipality"
                                                    value="{{ $u->city_municipality }}" placeholder="city"
                                                    style="margin-top:6px; width:100%; max-width:160px;">
                                                <input type="text" name="street" value="{{ $u->street }}"
                                                    placeholder="street"
                                                    style="margin-top:6px; width:100%; max-width:160px;">
                                            </td>
                                            <td>
                                                <select name="role" style="max-width:140px;">
                                                    <option value="developer"
                                                        {{ $u->role === 'developer' ? 'selected' : '' }}>
                                                        developer</option>
                                                    <option value="admin"
                                                        {{ $u->role === 'admin' ? 'selected' : '' }}>admin
                                                    </option>
                                                    <option value="member"
                                                        {{ $u->role === 'member' ? 'selected' : '' }}>
                                                        member
                                                    </option>
                                                    <option value="officer"
                                                        {{ $u->role === 'officer' ? 'selected' : '' }}>
                                                        officer</option>
                                                    <option value="inactive"
                                                        {{ $u->role === 'inactive' ? 'selected' : '' }}>
                                                        inactive</option>
                                                    <option value="terminated"
                                                        {{ $u->role === 'terminated' ? 'selected' : '' }}>terminated
                                                    </option>
                                                </select>
                                                <div style="margin-top:6px"><input type="password" name="password"
                                                        placeholder="new password (optional)"
                                                        style="max-width:180px;"></div>
                                            </td>
                                            <td class="actions">
                                                <button type="submit">Save</button>
                                        </form>

                                        <form method="POST" action="{{ route('dev.users.combined.delete') }}"
                                            style="display:inline">
                                            @csrf
                                            <input type="hidden" name="user_id" value="{{ $u->user_id }}">
                                            <input type="hidden" name="personal_data_id"
                                                value="{{ $u->personal_data_id }}">
                                            <button type="submit"
                                                onclick="return confirm('Delete this user and related records (if safe)?')">Delete</button>
                                        </form>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

    </div>

</body>

</html>
