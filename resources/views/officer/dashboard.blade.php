<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Officer Dashboard</title>
    <link rel="stylesheet" href="{{ asset('css/officer-dashboard.css') }}">
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
</head>

<body>
    <!-- Top Bar -->
    <div class="topbar">
        <div class="user-info">
            {{ $user->name }} ({{ strtoupper($user->role) }}) (ID: {{ $user->user_id }})
        </div>
        <form method="POST" action="{{ route('logout') }}" style="margin: 0;">
            @csrf
            <button type="submit" class="logout-btn">Logout</button>
        </form>
    </div>

    <!-- Layout Container -->
    <div class="layout">
        <!-- Sidebar -->
        <div class="sidebar">
            <ul>
                <li><a href="#" class="nav-link active" data-section="members">Members List</a></li>
                <li><a href="#" class="nav-link" data-section="accounts">Accounts</a></li>
                <li><a href="#" class="nav-link" data-section="loans">Loans</a></li>
            </ul>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Refresh Controls -->
            <div class="refresh-section">
                <button id="refresh-btn" class="refresh-btn">Refresh Data</button>
                <span class="last-updated" id="last-updated">
                    Last updated: {{ now()->format('M d, Y h:i A') }}
                </span>
            </div>

            <!-- Members Section -->
            <div id="members-section" class="content-section">
                <div class="section-header">
                    <h2>Members List</h2>
                </div>

                <div id="members-container">
                    @if ($members->count() > 0)
                        <table>
                            <thead>
                                <tr>
                                    <th>User ID</th>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Phone Number</th>
                                    <th>Role</th>
                                </tr>
                            </thead>
                            <tbody id="members-tbody">
                                @foreach ($members as $member)
                                    <tr>
                                        <td>{{ $member->user_id }}</td>
                                        <td>{{ trim(optional($member->personalData)->first_name . ' ' . optional($member->personalData)->last_name) }}
                                        </td>
                                        <td>{{ optional($member)->email ?? 'N/A' }}</td>
                                        <td>{{ optional($member->contact)->phone_no ?? 'N/A' }}</td>
                                        <td>{{ ucfirst($member->role) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @else
                        <div class="no-data">No members found.</div>
                    @endif
                </div>
            </div>

            <!-- Accounts Section -->
            <div id="accounts-section" class="content-section" style="display: none;">
                <div class="section-header">
                    <h2>Accounts</h2>
                </div>

                <div id="accounts-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Account ID</th>
                                <th>Owner</th>
                                <th>Type</th>
                                <th>Balance</th>
                            </tr>
                        </thead>
                        <tbody id="accounts-tbody">
                            <!-- Data loaded via AJAX -->
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Loans Section -->
            <div id="loans-section" class="content-section" style="display: none;">
                <div class="section-header">
                    <h2>Loans</h2>
                </div>

                <div id="loans-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Loan ID</th>
                                <th>Borrower</th>
                                <th>Loan Amount</th>
                                <th>Status</th>
                                <th>Due Date</th>
                            </tr>
                        </thead>
                        <tbody id="loans-tbody">
                            <!-- Data loaded via AJAX -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
        $(document).ready(function() {
            // Set up CSRF token for AJAX requests
            $.ajaxSetup({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                }
            });

            let currentSection = 'members'; // Track current active section

            // Sidebar navigation - switch between sections without page reload
            $('.nav-link').on('click', function(e) {
                e.preventDefault();

                // Update active state in sidebar
                $('.nav-link').removeClass('active');
                $(this).addClass('active');

                // Hide all sections
                $('.content-section').hide();

                // Show selected section
                currentSection = $(this).data('section');
                $('#' + currentSection + '-section').show();

                // Load data for the section if not already loaded
                if (currentSection === 'accounts' && $('#accounts-tbody tr').length === 0) {
                    loadAccounts();
                } else if (currentSection === 'loans' && $('#loans-tbody tr').length === 0) {
                    loadLoans();
                }
            });

            // Refresh button - reload data for current section
            $('#refresh-btn').on('click', function() {
                const btn = $(this);
                btn.prop('disabled', true).text('Refreshing...');

                // Call appropriate refresh function based on current section
                if (currentSection === 'members') {
                    refreshMembers(btn);
                } else if (currentSection === 'accounts') {
                    refreshAccounts(btn);
                } else if (currentSection === 'loans') {
                    refreshLoans(btn);
                }
            });

            // Load accounts data via AJAX
            function loadAccounts() {
                $.ajax({
                    url: '{{ route('officer.refresh.accounts') }}',
                    method: 'GET',
                    success: function(data) {
                        displayAccounts(data);
                    },
                    error: function() {
                        $('#accounts-tbody').html(
                            '<tr><td colspan="4" style="text-align:center; color:#e74c3c;">Failed to load accounts</td></tr>'
                        );
                    }
                });
            }

            // Load loans data via AJAX
            function loadLoans() {
                $.ajax({
                    url: '{{ route('officer.refresh.loans') }}',
                    method: 'GET',
                    success: function(data) {
                        displayLoans(data);
                    },
                    error: function() {
                        $('#loans-tbody').html(
                            '<tr><td colspan="5" style="text-align:center; color:#e74c3c;">Failed to load loans</td></tr>'
                        );
                    }
                });
            }

            // Refresh members list
            function refreshMembers(btn) {
                $.ajax({
                    url: '{{ route('officer.refresh.members') }}',
                    method: 'GET',
                    success: function(data) {
                        displayMembers(data);
                        updateTimestamp();
                        btn.prop('disabled', false).text('Refresh Data');
                    },
                    error: function() {
                        alert('Failed to refresh members data');
                        btn.prop('disabled', false).text('Refresh Data');
                    }
                });
            }

            // Refresh accounts list
            function refreshAccounts(btn) {
                $.ajax({
                    url: '{{ route('officer.refresh.accounts') }}',
                    method: 'GET',
                    success: function(data) {
                        displayAccounts(data);
                        updateTimestamp();
                        btn.prop('disabled', false).text('Refresh Data');
                    },
                    error: function() {
                        alert('Failed to refresh accounts data');
                        btn.prop('disabled', false).text('Refresh Data');
                    }
                });
            }

            // Refresh loans list
            function refreshLoans(btn) {
                $.ajax({
                    url: '{{ route('officer.refresh.loans') }}',
                    method: 'GET',
                    success: function(data) {
                        displayLoans(data);
                        updateTimestamp();
                        btn.prop('disabled', false).text('Refresh Data');
                    },
                    error: function() {
                        alert('Failed to refresh loans data');
                        btn.prop('disabled', false).text('Refresh Data');
                    }
                });

            }

            // display members table rows
            function displayMembers(members) {
                const tbody = $('#members-tbody');
                tbody.empty();

                if (members.length > 0) {
                    members.forEach(function(member) {
                        const row = `
                            <tr>
                                <td>${member.user_id}</td>
                                <td>${member.name}</td>
                                <td>${member.email}</td>
                                <td>${member.phone}</td>
                                <td>${member.role}</td>
                            </tr>
                        `;
                        tbody.append(row);
                    });
                } else {
                    tbody.html(
                        '<tr><td colspan="5" style="text-align:center; color:#999;">No members found.</td></tr>'
                    );
                }
            }

            // display accounts table rows
            function displayAccounts(accounts) {
                const tbody = $('#accounts-tbody');
                tbody.empty();

                if (accounts.length > 0) {
                    accounts.forEach(function(account) {
                        const row = `
                            <tr>
                                <td>${account.account_id}</td>
                                <td>${account.owner}</td>
                                <td>${account.account_type}</td>
                                <td>₱${account.balance}</td>
                            </tr>
                        `;
                        tbody.append(row);
                    });
                } else {
                    tbody.html(
                        '<tr><td colspan="4" style="text-align:center; color:#999;">No accounts found.</td></tr>'
                    );
                }
            }

            // display loans table rows
            function displayLoans(loans) {
                const tbody = $('#loans-tbody');
                tbody.empty();

                if (loans.length > 0) {
                    loans.forEach(function(loan) {
                        // Format due date
                        const dueDate = new Date(loan.due_date).toLocaleDateString('en-US', {
                            month: 'short',
                            day: 'numeric',
                            year: 'numeric'
                        });

                        const row = `
                            <tr>
                                <td>${loan.loan_id}</td>
                                <td>${loan.borrower}</td>
                                <td>₱${loan.loan_amount}</td>
                                <td><span class="status-badge ${loan.loan_status.toLowerCase()}">${loan.loan_status}</span></td>
                                <td>${dueDate}</td>
                            </tr>
                        `;
                        tbody.append(row);
                    });
                } else {
                    tbody.html(
                        '<tr><td colspan="5" style="text-align:center; color:#999;">No loans found.</td></tr>'
                    );
                }
            }

            // Update the last updated timestamp
            function updateTimestamp() {
                const now = new Date();
                $('#last-updated').text('Last updated: ' + now.toLocaleString('en-US', {
                    month: 'short',
                    day: 'numeric',
                    year: 'numeric',
                    hour: 'numeric',
                    minute: '2-digit',
                    hour12: true
                }));
            }

            // Auto-refresh every 30 seconds
            setInterval(function() {
                if (currentSection === 'members') {
                    $.get('{{ route('officer.refresh.members') }}', function(data) {
                        displayMembers(data);
                        updateTimestamp();
                    });
                } else if (currentSection === 'accounts') {
                    $.get('{{ route('officer.refresh.accounts') }}', function(data) {
                        displayAccounts(data);
                        updateTimestamp();
                    });
                } else if (currentSection === 'loans') {
                    $.get('{{ route('officer.refresh.loans') }}', function(data) {
                        displayLoans(data);
                        updateTimestamp();
                    });
                }
            }, 30000); // 30 seconds
        });
    </script>
</body>

</html>
