# Role-facing workflow bundles to inspect sequentially. Each bundle includes
# the page, its API boundary, authorization code, and the closest tests.
$ErrorActionPreference = "Stop"

$repositoryRoot = Split-Path -Parent $PSScriptRoot
Set-Location -LiteralPath $repositoryRoot

if ((git rev-parse --is-inside-work-tree).Trim() -ne "true") {
    throw "The pipeline must run from inside a Git worktree."
}

$openCodeCommand = (Get-Command "opencode.cmd" -CommandType Application -ErrorAction Stop).Source

function Assert-CleanWorktree {
    [string[]]$changes = @(git status --porcelain)
    if ($changes.Count -gt 0) {
        throw "Refusing to run in a dirty worktree. Commit, stash, or discard existing changes first."
    }
}

function Assert-GitHead {
    param([string]$ExpectedHead)

    $actualHead = (git rev-parse HEAD).Trim()
    if ($actualHead -ne $ExpectedHead) {
        throw "An OpenCode stage changed HEAD. Stages must not create, amend, reset, or switch commits."
    }
}

function Invoke-OpenCodeStage {
    param(
        [string]$Model,
        [string]$Prompt,
        [string]$RequiredArtifact
    )

    if ($RequiredArtifact) {
        Remove-Item -LiteralPath $RequiredArtifact -Force -ErrorAction SilentlyContinue
    }

    & $openCodeCommand run -m $Model $Prompt
    if ($LASTEXITCODE -ne 0) {
        throw "OpenCode stage failed for model '$Model' with exit code $LASTEXITCODE."
    }

    if ($RequiredArtifact -and (
        -not (Test-Path -LiteralPath $RequiredArtifact -PathType Leaf) -or
        (Get-Item -LiteralPath $RequiredArtifact).Length -eq 0
    )) {
        throw "OpenCode stage completed without creating a non-empty '$RequiredArtifact'."
    }
}

function Read-VerificationStatus {
    param([string]$ReportPath)

    $statusLine = Get-Content -LiteralPath $ReportPath |
        Where-Object { $_ -match '^STATUS:\s*(PASS|RETRY)\s*$' } |
        Select-Object -First 1

    if (-not $statusLine -or $statusLine -notmatch '^STATUS:\s*(PASS|RETRY)\s*$') {
        throw "Verification report '$ReportPath' must contain a line with STATUS: PASS or STATUS: RETRY."
    }

    return $Matches[1].ToUpperInvariant()
}

$workflows = @(
    @{
        Name = "member-management"
        Roles = "officer and admin users managing member records; members viewing their own data"
        Permissions = "members.view, members.create, members.update, members.delete, members.approve, members.view-full-details"
        Paths = @(
            "resources/react/pages/members",
            "app/Http/Controllers/Api/MemberController.php",
            "resources/react/api/psgc.ts",
            "routes/psgc.php",
            "app/Policies/MemberPolicy.php",
            "routes/api.php",
            "app/Http/Middleware/RoleMiddleware.php",
            "database/seeders/RolePermissionSeeder.php",
            "tests/Feature/MemberApplicationTest.php",
            "tests/Feature/ArchivedMembersTest.php",
            "tests/Feature/PsgcApiTest.php",
            "tests/Feature/UserScopesTest.php"
        )
    },
    @{
        Name = "services"
        Roles = "members initiating eligible services; officers reviewing or approving transactions"
        Permissions = "services.view, services.create, services.update, transactions.view, transactions.view-all, transactions.create, transactions.update, transactions.approve, deposits.view, deposits.create, deposits.approve, deposits.reject, withdrawals.view, withdrawals.create, withdrawals.approve, withdrawals.reject"
        Paths = @(
            "resources/react/pages/services",
            "app/Http/Controllers/Api/ServiceController.php",
            "app/Http/Controllers/Api/SettingsController.php",
            "app/Policies",
            "routes/api.php",
            "app/Support/PermissionCatalog.php",
            "database/seeders/RolePermissionSeeder.php",
            "database/seeders/SpatieRolePermissionSeeder.php",
            "tests/Feature/BusinessRulesApiTest.php",
            "tests/Feature/OtpJourneyTest.php"
        )
    },
    @{
        Name = "loans"
        Roles = "members applying for and viewing their loans; loan officers and authorized officers reviewing, disbursing, and closing loans"
        Permissions = "loans.view, loans.create, loans.encode, loans.update, loans.approve, loans.reject, loans.disburse"
        Paths = @(
            "resources/react/pages/loans",
            "resources/react/pages/member-loans",
            "resources/react/pages/loan-products",
            "app/Http/Controllers/Api/LoanController.php",
            "app/Http/Controllers/Api/LoanProductController.php",
            "app/Policies/LoanPolicy.php",
            "routes/api.php",
            "app/Support/PermissionCatalog.php",
            "database/seeders/RolePermissionSeeder.php",
            "database/seeders/SpatieRolePermissionSeeder.php",
            "tests/Feature/BusinessRulesApiTest.php",
            "tests/Feature/LoanReviewTest.php",
            "tests/Feature/LoanSeederTest.php"
        )
    },
    @{
        Name = "authentication-and-registration"
        Roles = "guests registering or signing in; members verifying email and recovering accounts"
        Permissions = "public registration, email verification, password reset, OTP verification"
        Paths = @(
            "resources/react/pages/auth",
            "resources/react/pages/public/RegisterPage.tsx",
            "resources/react/api/auth.ts",
            "resources/react/contexts/AuthContext.tsx",
            "app/Http/Controllers/Api/AuthController.php",
            "app/Http/Controllers/LoginController.php",
            "app/Http/Controllers/PasswordController.php",
            "app/Http/Controllers/PasswordResetController.php",
            "app/Http/Controllers/EmailVerificationController.php",
            "app/Http/Controllers/OtpChallengeController.php",
            "app/Http/Middleware",
            "routes/api.php",
            "routes/web.php",
            "tests/Feature/Auth"
        )
    },
    @{
        Name = "dashboard-and-overviews"
        Roles = "all authenticated members and officers, with role-specific dashboard overviews"
        Permissions = "dashboard.view, profile.view, notifications.view"
        Paths = @(
            "resources/react/pages/overviews",
            "resources/react/layouts/DashboardLayout.tsx",
            "resources/react/App.tsx",
            "resources/react/components/Sidebar.tsx",
            "resources/react/components/Header.tsx",
            "resources/react/components/NotificationDropdown.tsx",
            "resources/react/contexts/SidebarContext.tsx",
            "resources/react/contexts/AuthContext.tsx",
            "resources/react/contexts/ThemeContext.tsx",
            "resources/react/contexts/ToastContext.tsx",
            "config/sidebar.php",
            "app/Http/Controllers/Api/DashboardController.php",
            "app/Http/Controllers/Api/SearchController.php",
            "resources/react/api/search.ts",
            "routes/api.php",
            "tests/Feature/ReactApiTest.php",
            "tests/Feature/DashboardReloadTest.php",
            "tests/Feature/SearchApiTest.php",
            "tests/Feature/UserScopesTest.php"
        )
    },
    @{
        Name = "profile-and-account-settings"
        Roles = "authenticated members and officers managing their own profile, credentials, devices, and account lifecycle"
        Permissions = "profile.view, profile.update, settings.view, settings.update"
        Paths = @(
            "resources/react/pages/profile",
            "resources/react/pages/settings",
            "resources/react/api/profile.ts",
            "resources/react/api/settings.ts",
            "resources/react/lib/validation.ts",
            "app/Http/Controllers/Api/ProfileController.php",
            "app/Http/Controllers/Api/SettingsController.php",
            "app/Http/Controllers/ProfileController.php",
            "routes/api.php",
            "routes/web.php",
            "tests/Feature/ProfileTest.php",
            "tests/Feature/BusinessRulesApiTest.php",
            "tests/Feature/Auth/PasswordUpdateTest.php"
        )
    },
    @{
        Name = "notifications-and-history"
        Roles = "authenticated members and officers viewing, filtering, reading, and deleting their own notifications and transaction history"
        Permissions = "notifications.view, history.view"
        Paths = @(
            "resources/react/pages/notifications",
            "resources/react/pages/history",
            "resources/react/api/notifications.ts",
            "resources/react/api/history.ts",
            "resources/react/components/NotificationDropdown.tsx",
            "app/Http/Controllers/NotificationController.php",
            "app/Http/Controllers/Api/HistoryController.php",
            "app/Models/Notification.php",
            "routes/api.php",
            "tests/Feature/UserScopesTest.php",
            "tests/Feature/MemberNotificationMailTest.php",
            "tests/Feature/BusinessRulesApiTest.php"
        )
    },
    @{
        Name = "backup-and-service-settings"
        Roles = "administrators managing backups and authorized officers controlling service availability; members consuming available services"
        Permissions = "settings.view, settings.update, services.view, backups.view, backups.manage, backups.restore"
        Paths = @(
            "resources/react/pages/settings/BackupPage.tsx",
            "resources/react/pages/settings/ServiceAvailabilityPage.tsx",
            "resources/react/api/backups.ts",
            "resources/react/api/settings.ts",
            "app/Http/Controllers/Api/BackupController.php",
            "app/Http/Controllers/Api/SettingsController.php",
            "app/Services/BackupService.php",
            "app/Support/PermissionCatalog.php",
            "database/seeders/SpatieRolePermissionSeeder.php",
            "routes/api.php",
            "tests/Feature/BackupApiTest.php",
            "tests/Feature/BusinessRulesApiTest.php"
        )
    },
    @{
        Name = "communications-and-support"
        Roles = "members reading announcements and using support features; officers managing cooperative communications"
        Permissions = "announcements.view, announcements.create, announcements.update, messages.view, tickets.view"
        Paths = @(
            "resources/react/pages/announcements",
            "resources/react/pages/messages",
            "resources/react/pages/tickets",
            "resources/react/pages/public",
            "resources/react/pages/public-content",
            "resources/react/api/announcements.ts",
            "app/Http/Controllers/Api/AnnouncementController.php",
            "app/Http/Controllers/Api/PublicContentController.php",
            "routes/api.php",
            "routes/web.php",
            "database/seeders/RolePermissionSeeder.php",
            "tests/Feature/PublicContentManagementTest.php",
            "tests/Feature/MemberNotificationMailTest.php"
        )
    },
    @{
        Name = "officer-finance-and-reports"
        Roles = "officers using only their assigned operational features; administrators and finance roles using ledger, expenses, reports, branches, and officer management"
        Permissions = "officers.view, officers.create, officers.update, officers.delete, officers.manage-permissions, branches.view, branches.create, branches.update, branches.delete, transactions.view, transactions.view-all, transactions.create, transactions.update, transactions.approve, transactions.void, transactions.export, reports.view, reports.export, reports.financial, reports.audit, logs.view, logs.export"
        Paths = @(
            "resources/react/pages/officers",
            "resources/react/pages/ledger",
            "resources/react/pages/expenses",
            "resources/react/pages/reports",
            "resources/react/pages/branches",
            "resources/react/pages/logs",
            "resources/react/pages/role-permissions",
            "app/Http/Controllers/Api/OfficerPermissionController.php",
            "app/Http/Controllers/Api/RolePermissionController.php",
            "app/Http/Controllers/Api/LedgerController.php",
            "app/Http/Controllers/Api/ExpenseController.php",
            "app/Http/Controllers/Api/ReportController.php",
            "app/Http/Controllers/Api/BranchController.php",
            "app/Policies/ReportPolicy.php",
            "app/Policies/TransactionPolicy.php",
            "app/Policies/UserPolicy.php",
            "app/Http/Middleware/RoleMiddleware.php",
            "app/Support/PermissionCatalog.php",
            "database/seeders/SpatieRolePermissionSeeder.php",
            "routes/api.php",
            "tests/Feature/ReportsApiTest.php",
            "tests/Feature/BranchApiTest.php",
            "tests/Feature/UserScopesTest.php"
        )
    },
    @{
        Name = "permission-and-navigation-matrix"
        Roles = "all roles receiving navigation entries and permission-scoped API/page access"
        Permissions = "the complete PermissionCatalog and seeded role matrix, including dashboard, sidebar, search, and direct-route authorization"
        Paths = @(
            "resources/react/App.tsx",
            "resources/react/components/Sidebar.tsx",
            "resources/react/components/Header.tsx",
            "resources/react/components/NotificationDropdown.tsx",
            "config/sidebar.php",
            "app/Support/PermissionCatalog.php",
            "app/Http/Middleware/RoleMiddleware.php",
            "database/seeders/RolePermissionSeeder.php",
            "database/seeders/SpatieRolePermissionSeeder.php",
            "routes/api.php",
            "tests/Feature/UserScopesTest.php",
            "tests/Feature/LoanSeederTest.php",
            "tests/Feature/ReportsApiTest.php",
            "tests/Feature/BackupApiTest.php"
        )
    }
)

# OpenCode Zen model roles
$plannerModel    = "opencode/muse-spark-1.2-free"
$auditorModel    = "opencode/nemotron-3.5-lightning-free"
$coderModel      = "opencode/big-pickle"
$maxRepairAttempts = 3

# Keep agent artifacts outside the worktree so they cannot be accidentally committed.
$pipeDir = Join-Path ([System.IO.Path]::GetTempPath()) "opencode-audit-$PID"

Assert-CleanWorktree
$totalWorkflows = $workflows.Count
$workflowNumber = 0
Write-Host "Pipeline ready: $totalWorkflows workflows queued." -ForegroundColor Cyan
$confirmation = Read-Host "This will let OpenCode inspect and modify the repository, then create commits. Type YES to continue"
if ($confirmation -notmatch '^(?i:yes|y)$') {
    Write-Host "Pipeline cancelled. No files were changed." -ForegroundColor Yellow
    exit 0
}

New-Item -ItemType Directory -Path $pipeDir | Out-Null

foreach ($workflow in $workflows) {
    $workflowNumber++
    $name = $workflow.Name
    $paths = $workflow.Paths
    $workflowHead = (git rev-parse HEAD).Trim()
    $workflowStarted = Get-Date

    Write-Host "`n=======================================================" -ForegroundColor Cyan
    Write-Host " WORKFLOW [$workflowNumber/$totalWorkflows]: $name" -ForegroundColor Yellow
    Write-Host "=======================================================" -ForegroundColor Cyan

    $draftPlan   = "$pipeDir/${name}_draft_plan.md"
    $refinedPlan = "$pipeDir/${name}_approved_plan.md"
    $verificationReport = "$pipeDir/${name}_verification.md"
    $pathList = $paths -join "`n- "

    # --- STEP 1: ARCHITECTURAL PLANNING (Muse Spark 1.2) ---
    $stageStarted = Get-Date
    Write-Host "[1/4] Planning with Muse Spark (1M Context)..." -ForegroundColor Magenta
    $planPrompt = @"
Audit the '$name' user-facing workflow.
Inspect these page, route, controller, policy, permission, and test paths:
- $pathList

Primary actors: $($workflow.Roles)
Expected permissions: $($workflow.Permissions)

Trace each UI action to its API endpoint and authorization decision. Find logic flaws,
security vulnerabilities, missing validation, IDOR/data-scope problems, role confusion,
and mismatches between frontend PermissionGate checks and backend authorization.
For shared pages, compare member behavior with each officer role that can access the page.
Treat hidden navigation as insufficient security: verify the server-side enforcement.

For every finding, cite the exact file and function/line where the problem lives, and state
why the current behavior is wrong (what an attacker or misbehaving client could do). Do not
report a finding you cannot point to concrete code for. Rate each finding's severity
(critical/high/medium/low) so later stages can prioritize.

If a path shows no issues, say so explicitly rather than omitting it or inventing a minor
finding to pad the plan.

Do NOT modify any code.
Do NOT run Git add, commit, reset, restore, checkout, or switch commands.
Write an actionable, numbered implementation plan and save it directly to '$draftPlan'.
"@
    Invoke-OpenCodeStage -Model $plannerModel -Prompt $planPrompt -RequiredArtifact $draftPlan
    Assert-GitHead -ExpectedHead $workflowHead
    Write-Host ("    [DONE] Plan created ({0})" -f ((Get-Date) - $stageStarted).ToString("hh\:mm\:ss")) -ForegroundColor DarkCyan

    # --- STEP 2: ADVERSARIAL PLAN AUDIT (Nemotron 3.5 Lightning) ---
    $stageStarted = Get-Date
    Write-Host "[2/4] Auditing plan with Nemotron (Adversarial Gatekeeper)..." -ForegroundColor Magenta
    $auditPrompt = @"
You are a senior authorization and workflow auditor. Read '$draftPlan' and independently
re-inspect the same workflow paths yourself rather than trusting the draft's claims:
- $pathList

Actors: $($workflow.Roles)
Permissions: $($workflow.Permissions)

Check for:
1. Breaking schema/controller/page dependencies.
2. Backend authorization gaps, IDORs, over-broad queries, and role/permission bypasses.
3. Unhandled state transitions, validation, concurrency, OTP, and service-availability cases.
4. Member/officer feature leakage caused by relying only on React navigation or gates.
5. Ambiguous instructions or plans that lack a focused regression test.

Drop any draft finding you cannot confirm against the actual code at a specific file and
function/line; do not carry forward unverifiable or vague items. It is a valid and expected
outcome for this workflow to need no changes at all -- do not manufacture a finding just to
have something to report. Every retained item must cite its exact location and include a
concrete, narrow regression test.

Rewrite the plan into a strict, finalized implementation specification and save it to
'$refinedPlan'. If no changes are warranted, save a specification to '$refinedPlan' that
states this explicitly and instructs the next stage to make no changes.
Do NOT modify application code.
Do NOT run Git add, commit, reset, restore, checkout, or switch commands.
"@
    Invoke-OpenCodeStage -Model $auditorModel -Prompt $auditPrompt -RequiredArtifact $refinedPlan
    Assert-GitHead -ExpectedHead $workflowHead
    Write-Host ("    [DONE] Plan approved ({0})" -f ((Get-Date) - $stageStarted).ToString("hh\:mm\:ss")) -ForegroundColor DarkCyan

    $verificationStatus = "RETRY"
    $repairAttempt = 0
    while ($verificationStatus -eq "RETRY" -and $repairAttempt -lt $maxRepairAttempts) {
        $repairAttempt++

        # --- STEP 3: CODE IMPLEMENTATION (Big Pickle) ---
        $stageStarted = Get-Date
        Write-Host "[3/4] Implementing fixes with Big Pickle (attempt $repairAttempt/$maxRepairAttempts)..." -ForegroundColor Green
        if ($repairAttempt -eq 1) {
            $execPrompt = @"
Read the finalized specification in '$refinedPlan'. If it states that no changes are
warranted for this workflow, make none and stop.

Otherwise, implement only the fixes and tests described in that specification, in the
listed workflow paths. You may follow a dependency to a nearby model, request, policy,
middleware, route, or test when required to enforce the contract at the correct boundary,
but do not edit files outside those paths or their direct dependencies, and do not touch
unrelated workflows.

Do NOT create or modify database migrations or schema. If the specification's fix appears
to require a schema change, stop and report that instead of implementing one.

Never satisfy a failing test by weakening, removing, or bypassing an authorization check,
validation rule, or policy -- the fix must close the actual gap, not the test. If a test
itself is wrong given the intended behavior, say so in your output instead of changing
production code to match it.

Preserve framework conventions, use existing style patterns, and ensure complete, untruncated
functions. Never rely on frontend visibility for authorization. Preserve legitimate
differences between members and officers.
Do NOT run Git add, commit, reset, restore, checkout, or switch commands.
"@
        } else {
            $execPrompt = @"
Read the finalized specification in '$refinedPlan' and the previous verification report
at '$verificationReport'. Fix every remaining issue identified in that report, then rerun
the relevant focused tests and checks. Inspect the current diff before editing so you do not
undo valid fixes.

Only change this workflow and its direct dependencies. Do NOT create or modify database
migrations or schema. Never weaken authorization, validation, or policies to make tests pass.
Do NOT run Git add, commit, reset, restore, checkout, or switch commands.
"@
        }
        Invoke-OpenCodeStage -Model $coderModel -Prompt $execPrompt
        Assert-GitHead -ExpectedHead $workflowHead
        Write-Host ("    [DONE] Implementation stage finished ({0})" -f ((Get-Date) - $stageStarted).ToString("hh\:mm\:ss")) -ForegroundColor DarkCyan

        # --- STEP 4: VERIFICATION AUDIT (Nemotron 3.5 Lightning) ---
        $stageStarted = Get-Date
        Write-Host "[4/4] Verifying applied diff with Nemotron..." -ForegroundColor Green
        $verifyPrompt = @"
Inspect the latest git diff for the '$name' workflow and all changed files.
Run the narrowest relevant Laravel/Pest tests for this workflow, plus route and PHP/TypeScript
syntax checks where available. Verify member and officer authorization both positively and
negatively, including direct API requests that bypass the UI. Ensure no unintended regressions,
missing imports, or frontend/backend permission mismatches were introduced. Confirm no fix
weakened an authorization check, validation rule, or policy merely to make a test pass.

If minor syntax, import, or test failures remain within files already changed in this
workflow, fix them now and rerun the focused checks. Do not broaden the change to unrelated
workflows, and do not create or modify database migrations or schema.

If fixing a remaining failure would require editing a file that has not already been touched
in this workflow, or a schema/migration change, report the failure as RETRY instead of making
that edit yourself.

Write a concise report to '$verificationReport'. The report MUST contain exactly one status
line in this format: STATUS: PASS or STATUS: RETRY. Use STATUS: PASS only when the workflow
is fully verified and no actionable issue remains. Use STATUS: RETRY when any issue, failed
test, unverified authorization path, or blocker remains. Put the status line near the top,
then list the evidence, tests run, and any remaining issues.
Do NOT run Git add, commit, reset, restore, checkout, or switch commands.
"@
        Invoke-OpenCodeStage -Model $auditorModel -Prompt $verifyPrompt -RequiredArtifact $verificationReport
        Assert-GitHead -ExpectedHead $workflowHead
        $verificationStatus = Read-VerificationStatus -ReportPath $verificationReport
        Write-Host ("    [DONE] Verification attempt $repairAttempt returned $verificationStatus ({0})" -f ((Get-Date) - $stageStarted).ToString("hh\:mm\:ss")) -ForegroundColor DarkCyan
    }

    if ($verificationStatus -ne "PASS") {
        throw "Workflow '$name' did not pass verification after $maxRepairAttempts attempts. Review '$verificationReport'."
    }

    # --- GIT AUTO-COMMIT ---
    [string[]]$workflowChanges = @(git status --porcelain)
    if ($workflowChanges.Count -eq 0) {
        Write-Host (">>> FINISHED [$workflowNumber/$totalWorkflows] $name; no fixes were required. Total: {0}" -f ((Get-Date) - $workflowStarted).ToString("hh\:mm\:ss")) -ForegroundColor Green
        continue
    }

    Write-Host "Committing verified changes for $name..." -ForegroundColor DarkGray
    # The worktree was clean before the workflow and artifacts live outside it, so this
    # stages every verified implementation or test change, including justified dependencies.
    git add -A
    $changedFiles = ($workflowChanges | ForEach-Object { $_.Substring(3) }) -join "`n- "
    $commitMessage = "fix(workflow): audited and patched $name via pipeline`n`nFiles changed:`n- $changedFiles"
    git commit -m $commitMessage
    if ($LASTEXITCODE -ne 0) {
        throw "Git commit failed for workflow '$name' with exit code $LASTEXITCODE."
    }
    
    $commitHash = (git rev-parse --short HEAD).Trim()
    Write-Host (">>> FINISHED [$workflowNumber/$totalWorkflows] $name; committed $commitHash. Total: {0}" -f ((Get-Date) - $workflowStarted).ToString("hh\:mm\:ss")) -ForegroundColor Green
}

# Cleanup temporary markdown artifacts after a successful complete run.
Remove-Item -LiteralPath $pipeDir -Recurse -Force
Write-Host "`nALL WORKFLOWS PROCESSED SUCCESSFULLY." -ForegroundColor Cyan