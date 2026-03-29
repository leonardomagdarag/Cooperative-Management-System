import $ from 'jquery';

function getConfig() {
    const config = window.officerDashboard;

    if (!config || !config.urls) {
        throw new Error('Officer dashboard config is missing.');
    }

    return {
        csrfToken: config.csrfToken || '',
        urls: config.urls,
    };
}

$(document).ready(function () {
    const { csrfToken, urls } = getConfig();

    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': csrfToken,
        },
    });

    let currentSection = 'members';

    $('.nav-link').on('click', function (e) {
        e.preventDefault();

        $('.nav-link').removeClass('active');
        $(this).addClass('active');

        $('.content-section').hide();

        currentSection = $(this).data('section');
        $('#' + currentSection + '-section').show();

        if (currentSection === 'accounts' && $('#accounts-tbody tr').length === 0) {
            loadAccounts();
        } else if (currentSection === 'loans' && $('#loans-tbody tr').length === 0) {
            loadLoans();
        }
    });

    $('#refresh-btn').on('click', function () {
        const btn = $(this);
        btn.prop('disabled', true).text('Refreshing...');

        if (currentSection === 'members') {
            refreshMembers(btn);
        } else if (currentSection === 'accounts') {
            refreshAccounts(btn);
        } else if (currentSection === 'loans') {
            refreshLoans(btn);
        }
    });

    function loadAccounts() {
        $.ajax({
            url: urls.accounts,
            method: 'GET',
            success: function (data) {
                displayAccounts(data);
            },
            error: function () {
                $('#accounts-tbody').html(
                    '<tr><td colspan="4" style="text-align:center; color:#e74c3c;">Failed to load accounts</td></tr>'
                );
            },
        });
    }

    function loadLoans() {
        $.ajax({
            url: urls.loans,
            method: 'GET',
            success: function (data) {
                displayLoans(data);
            },
            error: function () {
                $('#loans-tbody').html(
                    '<tr><td colspan="5" style="text-align:center; color:#e74c3c;">Failed to load loans</td></tr>'
                );
            },
        });
    }

    function refreshMembers(btn) {
        $.ajax({
            url: urls.members,
            method: 'GET',
            success: function (data) {
                displayMembers(data);
                updateTimestamp();
                btn.prop('disabled', false).text('Refresh Data');
            },
            error: function () {
                alert('Failed to refresh members data');
                btn.prop('disabled', false).text('Refresh Data');
            },
        });
    }

    function refreshAccounts(btn) {
        $.ajax({
            url: urls.accounts,
            method: 'GET',
            success: function (data) {
                displayAccounts(data);
                updateTimestamp();
                btn.prop('disabled', false).text('Refresh Data');
            },
            error: function () {
                alert('Failed to refresh accounts data');
                btn.prop('disabled', false).text('Refresh Data');
            },
        });
    }

    function refreshLoans(btn) {
        $.ajax({
            url: urls.loans,
            method: 'GET',
            success: function (data) {
                displayLoans(data);
                updateTimestamp();
                btn.prop('disabled', false).text('Refresh Data');
            },
            error: function () {
                alert('Failed to refresh loans data');
                btn.prop('disabled', false).text('Refresh Data');
            },
        });
    }

    function displayMembers(members) {
        const tbody = $('#members-tbody');
        tbody.empty();

        if (Array.isArray(members) && members.length > 0) {
            members.forEach(function (member) {
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
            tbody.html('<tr><td colspan="5" style="text-align:center; color:#999;">No members found.</td></tr>');
        }
    }

    function displayAccounts(accounts) {
        const tbody = $('#accounts-tbody');
        tbody.empty();

        if (Array.isArray(accounts) && accounts.length > 0) {
            accounts.forEach(function (account) {
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
            tbody.html('<tr><td colspan="4" style="text-align:center; color:#999;">No accounts found.</td></tr>');
        }
    }

    function displayLoans(loans) {
        const tbody = $('#loans-tbody');
        tbody.empty();

        if (Array.isArray(loans) && loans.length > 0) {
            loans.forEach(function (loan) {
                const dueDate = new Date(loan.due_date).toLocaleDateString('en-US', {
                    month: 'short',
                    day: 'numeric',
                    year: 'numeric',
                });

                const row = `
                    <tr>
                        <td>${loan.loan_id}</td>
                        <td>${loan.borrower}</td>
                        <td>₱${loan.loan_amount}</td>
                        <td><span class="status-badge ${String(loan.loan_status).toLowerCase()}">${loan.loan_status}</span></td>
                        <td>${dueDate}</td>
                    </tr>
                `;
                tbody.append(row);
            });
        } else {
            tbody.html('<tr><td colspan="5" style="text-align:center; color:#999;">No loans found.</td></tr>');
        }
    }

    function updateTimestamp() {
        const now = new Date();
        $('#last-updated').text(
            'Last updated: ' +
                now.toLocaleString('en-US', {
                    month: 'short',
                    day: 'numeric',
                    year: 'numeric',
                    hour: 'numeric',
                    minute: '2-digit',
                    hour12: true,
                })
        );
    }

    setInterval(function () {
        if (currentSection === 'members') {
            $.get(urls.members, function (data) {
                displayMembers(data);
                updateTimestamp();
            });
        } else if (currentSection === 'accounts') {
            $.get(urls.accounts, function (data) {
                displayAccounts(data);
                updateTimestamp();
            });
        } else if (currentSection === 'loans') {
            $.get(urls.loans, function (data) {
                displayLoans(data);
                updateTimestamp();
            });
        }
    }, 30000);
});
