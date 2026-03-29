<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Officer Dashboard</title>
    <link rel="stylesheet" href="{{ asset('css/officer-dashboard.css') }}">
    <script>
        window.officerDashboard = {
            csrfToken: '{{ csrf_token() }}',
            urls: {
                members: '{{ route('officer.refresh.members') }}',
                accounts: '{{ route('officer.refresh.accounts') }}',
                loans: '{{ route('officer.refresh.loans') }}',
            }
        };
    </script>
    @vite(['resources/js/officer-dashboard.js'])
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

</body>

</html>
