# Role-facing workflow bundles to inspect sequentially. Each bundle includes
# the page, its API boundary, authorization code, and the closest tests.
$ErrorActionPreference = "Stop"

# Keep native command exit codes observable through $LASTEXITCODE. In PowerShell 7.2+,
# setting the global preference prevents native non-zero exits from becoming terminating
# PowerShell errors before the explicit $LASTEXITCODE checks below can run.
if (Get-Variable -Name PSNativeCommandUseErrorActionPreference -Scope Global -ErrorAction SilentlyContinue) {
    $global:PSNativeCommandUseErrorActionPreference = $false
}

$repositoryRoot = (git -C $PSScriptRoot rev-parse --show-toplevel).Trim()
if ($LASTEXITCODE -ne 0 -or -not $repositoryRoot) {
    throw "Unable to locate the repository root from '$PSScriptRoot'."
}
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
    if ($LASTEXITCODE -ne 0) {
        throw "Unable to read the current Git HEAD."
    }
    if ($actualHead -ne $ExpectedHead) {
        throw "An OpenCode stage changed HEAD. Stages must not create, amend, reset, or switch commits."
    }
}

function Get-NonPipelineGitChanges {
    [string[]]$changes = @(git status --porcelain=v1 --untracked-files=all -- . ':(exclude).pipeline/**')
    if ($LASTEXITCODE -ne 0) {
        throw "Unable to inspect Git status."
    }
    return $changes
}

function Assert-NoCodeChanges {
    param([string]$StageName)

    [string[]]$changes = @(Get-NonPipelineGitChanges)
    if ($changes.Count -gt 0) {
        $details = ($changes -join "`n- ")
        throw "OpenCode stage '$StageName' modified repository files even though it was instructed not to modify application code.`n- $details"
    }
}

function Invoke-OpenCodeStage {
    param(
        [string]$Model,
        [string]$Prompt,
        [string]$RequiredArtifact,
        [string]$StageName,
        [string]$LogDirectory,
        [int]$MaxAttempts = 3,
        [bool]$AcceptNonZeroExitWithValidArtifact = $true
    )

    for ($attempt = 1; $attempt -le $MaxAttempts; $attempt++) {
        $attemptLog = $null
        $attemptMeta = $null

        if ($LogDirectory) {
            if (-not (Test-Path -LiteralPath $LogDirectory -PathType Container)) {
                New-Item -ItemType Directory -Path $LogDirectory -Force | Out-Null
            }

            $attemptLog = Join-Path $LogDirectory ("{0}-attempt{1}.log" -f $StageName, $attempt)
            $attemptMeta = Join-Path $LogDirectory ("{0}-attempt{1}.meta.txt" -f $StageName, $attempt)

            @(
                "Stage: $StageName"
                "Model: $Model"
                "Attempt: $attempt/$MaxAttempts"
                "Started: $(Get-Date -Format o)"
                "RequiredArtifact: $RequiredArtifact"
                "WorkingDirectory: $(Get-Location)"
                "ExpectedArtifactParentExists: $(if ($RequiredArtifact) { Test-Path -LiteralPath (Split-Path -Parent $RequiredArtifact) -PathType Container } else { 'n/a' })"
            ) | Set-Content -LiteralPath $attemptMeta -Encoding UTF8
        }

        Write-Host "    [RUN] OpenCode stage '$StageName' with model '$Model' (attempt $attempt/$MaxAttempts)..." -ForegroundColor DarkGray

        # Stream output to the visible terminal and retain a text copy for artifact markers.
        # Writing the preference globally above keeps a non-zero native exit observable here.
        $capturedOutput = @()
        try {
            $capturedOutput = @(& $openCodeCommand run -m $Model $Prompt 2>&1 | ForEach-Object {
                $line = "$_"
                Write-Host $line
                $line
            })
            $stageExitCode = $LASTEXITCODE
        }
        catch {
            $stageExitCode = if ($null -ne $LASTEXITCODE) { $LASTEXITCODE } else { 1 }
            $capturedOutput = @("PowerShell/native-command exception: $($_.Exception.Message)")
        }

        if ($attemptLog) {
            $capturedOutput | ForEach-Object { "$_" } | Set-Content -LiteralPath $attemptLog -Encoding UTF8
            Add-Content -LiteralPath $attemptMeta -Value "Finished: $(Get-Date -Format o)"
            Add-Content -LiteralPath $attemptMeta -Value "ExitCode: $stageExitCode"
            Add-Content -LiteralPath $attemptMeta -Value "CapturedOutputLines: $($capturedOutput.Count)"
        }

        # IMPORTANT: for artifact-producing stages, the script owns the artifact write.
        # The model emits the artifact between explicit markers; this avoids depending on
        # an agent successfully navigating a Windows filesystem path.
        if ($RequiredArtifact -and
            -not (
                (Test-Path -LiteralPath $RequiredArtifact -PathType Leaf) -and
                ((Get-Item -LiteralPath $RequiredArtifact).Length -gt 0)
            )) {
            $allOutput = ($capturedOutput | ForEach-Object { "$_" }) -join "`n"
            $beginMarker = '===BEGIN_ARTIFACT==='
            $endMarker = '===END_ARTIFACT==='
            $beginIndex = $allOutput.IndexOf($beginMarker, [System.StringComparison]::Ordinal)
            $endIndex = if ($beginIndex -ge 0) {
                $allOutput.IndexOf($endMarker, $beginIndex + $beginMarker.Length, [System.StringComparison]::Ordinal)
            } else { -1 }

            if ($beginIndex -ge 0 -and $endIndex -gt $beginIndex) {
                $artifactTextStart = $beginIndex + $beginMarker.Length
                $artifactText = $allOutput.Substring($artifactTextStart, $endIndex - $artifactTextStart).Trim()

                if ($artifactText.Length -gt 0) {
                    $artifactParent = Split-Path -Parent $RequiredArtifact
                    New-Item -ItemType Directory -Path $artifactParent -Force | Out-Null
                    Set-Content -LiteralPath $RequiredArtifact -Value $artifactText -Encoding UTF8
                    Write-Host "    [INFO] Reconstructed required artifact from OpenCode output: $RequiredArtifact" -ForegroundColor DarkCyan
                }
            }
        }

        $artifactIsValid = -not $RequiredArtifact -or (
            (Test-Path -LiteralPath $RequiredArtifact -PathType Leaf) -and
            (Get-Item -LiteralPath $RequiredArtifact).Length -gt 0
        )

        if ($RequiredArtifact -and -not $artifactIsValid) {
            Write-Host "    [DIAG] Required artifact was not found or is empty: $RequiredArtifact" -ForegroundColor DarkYellow
            $artifactParent = Split-Path -Parent $RequiredArtifact

            if (Test-Path -LiteralPath $artifactParent -PathType Container) {
                $nearbyArtifacts = @(Get-ChildItem -LiteralPath $artifactParent -File -ErrorAction SilentlyContinue |
                    Sort-Object LastWriteTime -Descending |
                    Select-Object -First 12 Name,Length,LastWriteTime)

                if ($nearbyArtifacts.Count -gt 0) {
                    Write-Host "    [DIAG] Recent files in artifact directory:" -ForegroundColor DarkYellow
                    $nearbyArtifacts | ForEach-Object {
                        Write-Host ("        {0} | {1} bytes | {2}" -f $_.Name, $_.Length, $_.LastWriteTime.ToString("yyyy-MM-dd HH:mm:ss.fff")) -ForegroundColor DarkYellow
                    }
                }
                else {
                    Write-Host "    [DIAG] Artifact directory exists but contains no files: $artifactParent" -ForegroundColor DarkYellow
                }
            }
            else {
                Write-Host "    [DIAG] Artifact directory does not exist: $artifactParent" -ForegroundColor DarkYellow
            }
        }

        if ($artifactIsValid -and $stageExitCode -eq 0) {
            return
        }

        if ($artifactIsValid -and $stageExitCode -ne 0 -and $AcceptNonZeroExitWithValidArtifact) {
            $logNote = if ($attemptLog) { " Output log: $attemptLog" } else { "" }
            Write-Host "    [WARN] Stage '$StageName' produced the required artifact but exited with code $stageExitCode. Keeping the artifact and continuing.$logNote" -ForegroundColor Yellow
            return
        }

        if ($attempt -lt $MaxAttempts) {
            $delaySeconds = 5 * $attempt
            $reason = if ($stageExitCode -ne 0) {
                "exit code $stageExitCode"
            }
            else {
                "missing or empty required artifact"
            }
            $logNote = if ($attemptLog) { " See $attemptLog" } else { "" }
            Write-Host "    [RETRY] OpenCode stage '$StageName' failed ($reason). Retrying in $delaySeconds seconds ($attempt/$MaxAttempts)...$logNote" -ForegroundColor Yellow
            Start-Sleep -Seconds $delaySeconds
        }
    }

    $finalLogNote = if ($attemptLog) { " Last output log: $attemptLog" } else { "" }

    if ($stageExitCode -ne 0) {
        throw "OpenCode stage '$StageName' failed for model '$Model' after $MaxAttempts attempts with exit code $stageExitCode.$finalLogNote"
    }

    throw "OpenCode stage '$StageName' completed after $MaxAttempts attempts without creating a non-empty '$RequiredArtifact'.$finalLogNote"
}

function Read-VerificationStatus {
    param([string]$ReportPath)

    $statusPattern = '^\s*STATUS:\s*(PASS|RETRY)\s*$'
    [string[]]$statusLines = @(Get-Content -LiteralPath $ReportPath |
        Where-Object { $_ -match $statusPattern })

    if ($statusLines.Count -ne 1) {
        throw "Verification report '$ReportPath' must contain exactly one unformatted STATUS: PASS or STATUS: RETRY line; found $($statusLines.Count)."
    }

    $null = $statusLines[0] -match $statusPattern
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
$plannerModel    = "opencode/muse-spark-1.3-contributor-free"
$auditorModel    = "opencode/nemotron-3-ultra-free"
$coderModel      = "opencode/big-pickle"
$maxRepairAttempts = 3

# Use a per-run identifier so concurrent/stale runs cannot collide on branches, worktrees,
# or preserved failure artifacts.
$runId = "{0}-{1}" -f (Get-Date -Format "yyyyMMdd-HHmmssfff"), ([guid]::NewGuid().ToString("N").Substring(0, 8))
# Failed-workflow artifacts are copied here before their worktree is discarded.
$failureArtifactDir = Join-Path $repositoryRoot (".pipeline\failures\{0}" -f $runId)

Assert-CleanWorktree
$totalWorkflows = $workflows.Count
$workflowNumber = 0
$successfulWorkflows = [System.Collections.Generic.List[string]]::new()
$failedWorkflows = [System.Collections.Generic.List[string]]::new()
$workflowIssues = [System.Collections.Generic.List[string]]::new()
$worktreeParent = Split-Path -Parent $repositoryRoot
Write-Host "Pipeline ready: $totalWorkflows workflows queued." -ForegroundColor Cyan
Write-Host "Failure diagnostics will be preserved under: $failureArtifactDir" -ForegroundColor DarkGray
$confirmation = Read-Host "This will let OpenCode inspect and modify the repository, then create commits. Type YES to continue"
if ($confirmation -notmatch '^(?i:yes|y)$') {
    Write-Host "Pipeline cancelled. No files were changed." -ForegroundColor Yellow
    exit 0
}

$worktreePruneOutput = git worktree prune --expire now 2>&1
if ($LASTEXITCODE -ne 0) {
    throw "Unable to prune stale Git worktree metadata: $worktreePruneOutput"
}

New-Item -ItemType Directory -Path $failureArtifactDir -Force | Out-Null

foreach ($workflow in $workflows) {
    $workflowNumber++
    $name = $workflow.Name
    $paths = $workflow.Paths
    $workflowHead = (git rev-parse HEAD).Trim()
    $workflowStarted = Get-Date
    $branchName = "audit/$name-$runId"
    $worktreePath = Join-Path $worktreeParent "$($name)-audit-$runId"
    $workflowPipeDir = Join-Path $worktreePath ".pipeline"
    $workflowSucceeded = $false
    $unmergedCommitExists = $false
    $cleanupFailure = $null

    Write-Host "`n=======================================================" -ForegroundColor Cyan
    Write-Host " WORKFLOW [$workflowNumber/$totalWorkflows]: $name" -ForegroundColor Yellow
    Write-Host "=======================================================" -ForegroundColor Cyan

    try {
        if (Test-Path -LiteralPath $worktreePath) {
            throw "Worktree path already exists: $worktreePath"
        }

        Write-Host "Creating isolated worktree: $worktreePath" -ForegroundColor DarkCyan
        git worktree add -b $branchName $worktreePath $workflowHead
        if ($LASTEXITCODE -ne 0) {
            throw "Unable to create isolated worktree for '$name'."
        }
        Set-Location -LiteralPath $worktreePath
        New-Item -ItemType Directory -Path $workflowPipeDir | Out-Null

    # OpenCode can write inside its active worktree; .pipeline is excluded from commits below.
    $draftPlan   = "$workflowPipeDir/${name}_draft_plan.md"
    $refinedPlan = "$workflowPipeDir/${name}_approved_plan.md"
    $verificationReport = "$workflowPipeDir/${name}_verification.md"
    $pathList = $paths -join "`n- "

    # --- STEP 1: ARCHITECTURAL PLANNING (Muse Spark 1.2) ---
    $stageStarted = Get-Date
    Write-Host "[1/4] Planning with Muse Spark..." -ForegroundColor Magenta
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

For React pages and components, also inspect consistency with the existing design system:
reuse established components, layout patterns, spacing, typography, colors, icons, form
controls, loading/empty/error states, and responsive behavior. Identify visual or interaction
inconsistencies that make the workflow feel broken or inaccessible, but do not propose a broad
redesign when the existing pattern already works.

For every finding, cite the exact file and function/line where the problem lives, and state
why the current behavior is wrong (what an attacker or misbehaving client could do). Do not
report a finding you cannot point to concrete code for. Rate each finding's severity
(critical/high/medium/low) so later stages can prioritize.

If a path shows no issues, say so explicitly rather than omitting it or inventing a minor
finding to pad the plan.

Do NOT modify any code.
Do NOT run Git add, commit, reset, restore, checkout, or switch commands.
Write an actionable, numbered implementation plan.
Do NOT use a file-writing tool for the final deliverable. In your final response, output the complete
plan between these exact markers, with no commentary outside them:
===BEGIN_ARTIFACT===
[complete plan]
===END_ARTIFACT===
The pipeline will save that content to '$draftPlan' itself.
"@
    Invoke-OpenCodeStage -Model $plannerModel -Prompt $planPrompt -RequiredArtifact $draftPlan -StageName "planner" -LogDirectory (Join-Path (Join-Path $failureArtifactDir $name) "logs")
    Assert-GitHead -ExpectedHead $workflowHead
    Assert-NoCodeChanges -StageName "planner"
    Write-Host ("    [DONE] Plan created ({0})" -f ((Get-Date) - $stageStarted).ToString("hh\:mm\:ss")) -ForegroundColor DarkCyan

    # --- STEP 2: ADVERSARIAL PLAN AUDIT ---
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
5. Inconsistent UI patterns, missing responsive states, accessibility problems, and duplicated
    components where an existing shared component should be reused.
6. Ambiguous instructions or plans that lack a focused regression test.

Drop any draft finding you cannot confirm against the actual code at a specific file and
function/line; do not carry forward unverifiable or vague items. It is a valid and expected
outcome for this workflow to need no changes at all -- do not manufacture a finding just to
have something to report. Every retained item must cite its exact location and include a
concrete, narrow regression test.

Rewrite the plan into a strict, finalized implementation specification. If no changes are warranted,
save a specification stating this explicitly and instructing the next stage to make no changes.
Do NOT use a file-writing tool for the final deliverable. In your final response, output the complete
specification between these exact markers, with no commentary outside them:
===BEGIN_ARTIFACT===
[complete specification]
===END_ARTIFACT===
The pipeline will save that content to '$refinedPlan' itself.
Do NOT modify application code.
Do NOT run Git add, commit, reset, restore, checkout, or switch commands.
"@
    Invoke-OpenCodeStage -Model $auditorModel -Prompt $auditPrompt -RequiredArtifact $refinedPlan -StageName "plan-auditor" -LogDirectory (Join-Path (Join-Path $failureArtifactDir $name) "logs")
    Assert-GitHead -ExpectedHead $workflowHead
    Assert-NoCodeChanges -StageName "plan-auditor"
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
differences between members and officers. For frontend changes, make the design consistent
with neighboring pages by reusing existing components and conventions for layout, spacing,
typography, colors, icons, forms, feedback states, responsiveness, and accessibility. Fix
only concrete inconsistencies or usability defects; do not introduce a new visual language.
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
For frontend changes, preserve the existing design system and fix concrete inconsistencies
in layout, spacing, typography, colors, icons, forms, feedback states, responsiveness, and
accessibility rather than introducing a new visual language.
Do NOT run Git add, commit, reset, restore, checkout, or switch commands.
"@
        }
        Invoke-OpenCodeStage -Model $coderModel -Prompt $execPrompt -StageName "implementation-$repairAttempt" -LogDirectory (Join-Path (Join-Path $failureArtifactDir $name) "logs") -AcceptNonZeroExitWithValidArtifact:$false
        Assert-GitHead -ExpectedHead $workflowHead
        Write-Host ("    [DONE] Implementation stage finished ({0})" -f ((Get-Date) - $stageStarted).ToString("hh\:mm\:ss")) -ForegroundColor DarkCyan

        # --- STEP 4: VERIFICATION AUDIT ---
        $stageStarted = Get-Date
        Write-Host "[4/4] Verifying applied diff with Nemotron..." -ForegroundColor Green
        $verifyPrompt = @"
Read the finalized specification in '$refinedPlan' first, then inspect the latest git diff for the '$name' workflow and all changed files.
Treat every actionable item in that specification as a requirement: verify that each required fix and regression test was actually implemented, or explicitly justify why a specified item is no longer necessary based on the current code. Do not mark PASS merely because the code happens to be test-green while silently skipping specification items.
Run the narrowest relevant Laravel/Pest tests for this workflow, plus route and PHP/TypeScript
syntax checks where available. Verify member and officer authorization both positively and
negatively, including direct API requests that bypass the UI. Ensure no unintended regressions,
missing imports, or frontend/backend permission mismatches were introduced. Confirm no fix
weakened an authorization check, validation rule, or policy merely to make a test pass. For
frontend changes, verify the result is consistent with neighboring pages and shared components
in layout, spacing, typography, colors, icons, forms, loading/empty/error states, responsive
behavior, keyboard access, and other relevant accessibility behavior.

If minor syntax, import, or test failures remain within files already changed in this
workflow, fix them now and rerun the focused checks. Do not broaden the change to unrelated
workflows, and do not create or modify database migrations or schema.

If fixing a remaining failure would require editing a file that has not already been touched
in this workflow, or a schema/migration change, report the failure as RETRY instead of making
that edit yourself.

Write a concise verification report. The report MUST contain exactly one status
line in this format: STATUS: PASS or STATUS: RETRY, with no Markdown formatting, bullet,
backticks, or extra characters on that line. Use STATUS: PASS only when the workflow
is fully verified and no actionable issue remains. Use STATUS: RETRY when any issue, failed
test, unverified authorization path, or blocker remains. Put the status line near the top,
then list the evidence, tests run, and any remaining issues.

Do NOT use a file-writing tool for the final deliverable. In your final response, output the complete
report between these exact markers, with no commentary outside them:
===BEGIN_ARTIFACT===
[complete verification report]
===END_ARTIFACT===
The pipeline will save that content to '$verificationReport' itself.
Do NOT run Git add, commit, reset, restore, checkout, or switch commands.
"@
        Invoke-OpenCodeStage -Model $auditorModel -Prompt $verifyPrompt -RequiredArtifact $verificationReport -StageName "verification-$repairAttempt" -LogDirectory (Join-Path (Join-Path $failureArtifactDir $name) "logs")
        Assert-GitHead -ExpectedHead $workflowHead
        $verificationStatus = Read-VerificationStatus -ReportPath $verificationReport
        Write-Host ("    [DONE] Verification attempt $repairAttempt returned $verificationStatus ({0})" -f ((Get-Date) - $stageStarted).ToString("hh\:mm\:ss")) -ForegroundColor DarkCyan
    }

    if ($verificationStatus -ne "PASS") {
        throw "Workflow '$name' did not pass verification after $maxRepairAttempts attempts. Review '$verificationReport'."
    }

    # --- GIT AUTO-COMMIT ---
    [string[]]$workflowChanges = @(Get-NonPipelineGitChanges)
    if ($workflowChanges.Count -eq 0) {
        Set-Location -LiteralPath $repositoryRoot
        git merge --ff-only $branchName | Out-Null
        if ($LASTEXITCODE -ne 0) { throw "Fast-forward merge failed for '$name'." }
        $successfulWorkflows.Add($name)
        $workflowSucceeded = $true
        Write-Host (">>> FINISHED [$workflowNumber/$totalWorkflows] $name; no fixes were required. Total: {0}" -f ((Get-Date) - $workflowStarted).ToString("hh\:mm\:ss")) -ForegroundColor Green
    } else {
        Write-Host "Committing verified changes for $name in isolated worktree..." -ForegroundColor DarkGray
        git add -A -- . ':(exclude).pipeline'
        $changedFiles = ($workflowChanges | ForEach-Object { $_.Substring(3) }) -join "`n- "
        $commitMessage = "fix(workflow): audited and patched $name via pipeline`n`nFiles changed:`n- $changedFiles"
        git commit -m $commitMessage
        if ($LASTEXITCODE -ne 0) {
            throw "Git commit failed for workflow '$name' with exit code $LASTEXITCODE."
        }

        $commitHash = (git rev-parse --short HEAD).Trim()
        $unmergedCommitExists = $true
        Set-Location -LiteralPath $repositoryRoot
        git merge --ff-only $branchName | Out-Null
        if ($LASTEXITCODE -ne 0) { throw "Fast-forward merge failed for '$name'." }
        $unmergedCommitExists = $false
        $successfulWorkflows.Add($name)
        $workflowSucceeded = $true
        Write-Host (">>> FINISHED [$workflowNumber/$totalWorkflows] $name; merged $commitHash into main. Total: {0}" -f ((Get-Date) - $workflowStarted).ToString("hh\:mm\:ss")) -ForegroundColor Green
    }
    } catch {
        if (Test-Path -LiteralPath $workflowPipeDir) {
            $preservedFailureDir = Join-Path $failureArtifactDir "$name-$runId"
            Copy-Item -LiteralPath $workflowPipeDir -Destination $preservedFailureDir -Recurse -Force
            Write-Host "[DEBUG] Preserved workflow diagnostics at: $preservedFailureDir" -ForegroundColor DarkYellow
        }
        Set-Location -LiteralPath $repositoryRoot
        $issue = "$name attempt failed: $($_.Exception.Message)"
        $workflowIssues.Add($issue)
        Write-Host "[ISSUE] $issue" -ForegroundColor Red
        Write-Host "Discarding this worktree and proceeding to the next workflow." -ForegroundColor Yellow
    } finally {
        Set-Location -LiteralPath $repositoryRoot
        if (Test-Path -LiteralPath $worktreePath) {
            git worktree remove --force -- $worktreePath 2>$null
            if ($LASTEXITCODE -ne 0) {
                $cleanupFailure = "Failed to remove worktree '$worktreePath'."
            }
        }
        if (-not $cleanupFailure) {
            git show-ref --verify --quiet "refs/heads/$branchName"
            $branchExists = $LASTEXITCODE -eq 0
        } else {
            $branchExists = $false
        }
        if ($branchExists) {
            if ($unmergedCommitExists) {
                Write-Host "Preserving unmerged recovery branch '$branchName'." -ForegroundColor Yellow
            } else {
                git branch -D $branchName 2>$null | Out-Null
                if ($LASTEXITCODE -ne 0) {
                    $cleanupFailure = "Failed to delete temporary branch '$branchName'."
                }
            }
        }
    }

    if ($cleanupFailure) {
        $workflowIssues.Add($cleanupFailure)
        Write-Host "[ISSUE] $cleanupFailure Clean it up manually before the next run." -ForegroundColor Red
    }
    if (-not $workflowSucceeded) {
        $failedWorkflows.Add($name)
    }
}

# Only clean up temporary markdown artifacts after a fully successful run. If any workflow
# failed, its draft plan, refined plan, and verification report are the primary evidence for
# what went wrong, so keep them on disk rather than deleting them with everything else.
if ($failedWorkflows.Count -eq 0) {
    Remove-Item -LiteralPath $failureArtifactDir -Recurse -Force
} else {
    Write-Host "`nArtifacts for failed/skipped workflows preserved at: $failureArtifactDir" -ForegroundColor Yellow
}
Write-Host "`nPIPELINE SUMMARY" -ForegroundColor Cyan
Write-Host ("Successful: {0}/{1}" -f $successfulWorkflows.Count, $totalWorkflows) -ForegroundColor Green
Write-Host ("Skipped workflows: {0}" -f $failedWorkflows.Count) -ForegroundColor Yellow
Write-Host ("Issues: {0}" -f $workflowIssues.Count) -ForegroundColor Yellow
if ($workflowIssues.Count -gt 0) {
    Write-Host "Issues:" -ForegroundColor Red
    $workflowIssues | ForEach-Object { Write-Host "- $_" -ForegroundColor Red }
}
if ($failedWorkflows.Count -eq 0) {
    Write-Host "ALL WORKFLOWS PROCESSED SUCCESSFULLY." -ForegroundColor Cyan
} else {
    Write-Host "PIPELINE FINISHED WITH SKIPPED WORKFLOWS." -ForegroundColor Yellow
}
