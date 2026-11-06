import SortableHeader from "../../components/ui/SortableHeader";
import DateRangeFilter from "../../components/ui/DateRangeFilter";
import PageHeader from "../../components/ui/PageHeader";
import {
    cloneElement,
    useEffect,
    useId,
    useState,
    type ReactElement,
} from "react";
import { useQuery } from "@tanstack/react-query";
import { Bar } from "react-chartjs-2";
import {
    Chart as ChartJS,
    BarElement,
    CategoryScale,
    LinearScale,
    Tooltip,
    Legend,
} from "chart.js";
import { api } from "../../api/client";
import { useAuth } from "../../contexts/AuthContext";
import { useToast } from "../../contexts/ToastContext";
import Pagination from "../../components/ui/Pagination";
import InterestDisbursementsReport from "./InterestDisbursementsReport";

ChartJS.register(BarElement, CategoryScale, LinearScale, Tooltip, Legend);

type Column = {
    key: string;
    label: string;
    type?: "money" | "date" | "percent";
};
type Report = {
    key: string;
    title: string;
    group: string;
    icon: string;
    endpoint: string;
    exportType: string;
    description: string;
    note?: string;
    permission?: string;
    columns?: Column[];
};
const REPORTS: Report[] = [
    {
        key: "financial",
        title: "Financial Summary",
        group: "Overview",
        icon: "fa-chart-line",
        endpoint: "financial",
        exportType: "financial_report",
        description:
            "A period summary of deposits, withdrawals, lending, fees and expenses.",
        note: "Collected loan interest is estimated and shown separately from interest credits.",
    },
    {
        key: "balance_sheet",
        title: "Balance Sheet",
        group: "Overview",
        icon: "fa-scale-balanced",
        endpoint: "balance-sheet",
        exportType: "balance_sheet",
        permission: "reports.financial",
        description: "Management report - simplified financial position.",
        note: "Statement of financial position as of selected date. Cash reflects actual cash transactions (excluding member interest credits and savings-credited releases); loans receivable reflects active disbursed principal; savings and share capital reflect balances through the selected date. Retained earnings represents accumulated net surplus.",
    },
    {
        key: "member_savings",
        title: "Member Savings",
        group: "Savings & Interest",
        icon: "fa-piggy-bank",
        endpoint: "member-savings",
        exportType: "member_savings",
        description:
            "Deposits, withdrawals and interest credits by member, using transaction dates.",
        note: "Activity uses the selected dates. Current savings balances are live balances, not historical closing balances.",
        columns: [
            { key: "member_name", label: "Member" },
            { key: "total_deposits", label: "Deposits", type: "money" },
            { key: "total_withdrawals", label: "Withdrawals", type: "money" },
            {
                key: "total_interest_credits",
                label: "Interest credits",
                type: "money",
            },
            { key: "current_balance", label: "Current savings", type: "money" },
        ],
    },
    {
        key: "interest",
        title: "Interest Credits",
        group: "Savings & Interest",
        icon: "fa-coins",
        endpoint: "interest-disbursements",
        exportType: "interest_disbursements",
        permission: "interest.view",
        description:
            "Interest credit batches by creation date, with member-level details.",
    },
    {
        key: "loans",
        title: "Loan Register",
        group: "Loans & Collections",
        icon: "fa-file-invoice-dollar",
        endpoint: "loans",
        exportType: "loans",
        description:
            "Loan applications in the selected period, with their current status and balances.",
        note: "Dates filter application dates. Paid and remaining amounts reflect current balances.",
        columns: [
            { key: "loan_number", label: "Loan number" },
            { key: "member_name", label: "Member" },
            { key: "loan_amount", label: "Principal", type: "money" },
            { key: "interest_rate", label: "Rate", type: "percent" },
            { key: "loan_date", label: "Application date", type: "date" },
            { key: "due_date", label: "Due date", type: "date" },
            { key: "amount_paid", label: "Paid to date", type: "money" },
            {
                key: "remaining_balance",
                label: "Current remaining",
                type: "money",
            },
            { key: "status", label: "Status" },
        ],
    },
    {
        key: "payments",
        title: "Loan Payment Collections",
        group: "Loans & Collections",
        icon: "fa-money-bill-wave",
        endpoint: "payments",
        exportType: "payments",
        description:
            "Completed loan payments by payment date, net of reversals.",
        note: "Loan payments are externally funded. Loan balances shown are current.",
        columns: [
            { key: "member_name", label: "Member" },
            { key: "loan_number", label: "Loan number" },
            { key: "amount", label: "Net payment", type: "money" },
            { key: "payment_date", label: "Payment date", type: "date" },
            {
                key: "remaining_balance",
                label: "Current loan balance",
                type: "money",
            },
        ],
    },
    {
        key: "member_activity",
        title: "Transaction History",
        group: "Transactions & Expenses",
        icon: "fa-list-check",
        endpoint: "member-activity",
        exportType: "member_activity",
        description:
            "Completed member transactions by transaction date, with reversals shown as negative amounts.",
        columns: [
            { key: "transaction_number", label: "Reference" },
            { key: "member_name", label: "Member" },
            { key: "transaction_type", label: "Type" },
            { key: "amount", label: "Net amount", type: "money" },
            {
                key: "transaction_date",
                label: "Transaction date",
                type: "date",
            },
        ],
    },
    {
        key: "expenses",
        title: "Operating Expenses",
        group: "Transactions & Expenses",
        icon: "fa-receipt",
        endpoint: "expenses",
        exportType: "expenses",
        description:
            "Recorded operating expenses by expense date and category.",
        columns: [
            { key: "category", label: "Category" },
            { key: "description", label: "Description" },
            { key: "amount", label: "Amount", type: "money" },
            { key: "expense_date", label: "Expense date", type: "date" },
            { key: "recorded_by", label: "Recorded by" },
        ],
    },
];
const GROUPS = [...new Set(REPORTS.map((report) => report.group))];
const selectCls =
    "w-full rounded-lg border border-emerald-100 bg-white px-3 py-2 text-sm text-gray-700 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-100";
const money = (value: unknown) =>
    new Intl.NumberFormat("en-PH", {
        style: "currency",
        currency: "PHP",
    }).format(Number(value ?? 0));
const date = (value: string) =>
    value
        ? new Date(value.slice(0, 10) + "T00:00:00").toLocaleDateString(
              "en-PH",
              { year: "numeric", month: "short", day: "numeric" },
          )
        : "\u2014";
const label = (value: string) => value?.replace(/_/g, " ") ?? "\u2014";
function Field({
    title,
    children,
}: {
    title: string;
    children: ReactElement<{ id?: string }>;
}) {
    const id = useId();
    return (
        <div className="flex min-w-32 flex-1 flex-col gap-1 text-xs font-medium text-gray-600">
            <label htmlFor={id}>{title}</label>
            {cloneElement(children, { id })}
        </div>
    );
}
function MemberSearch({
    members,
    value,
    onChange,
}: {
    members: any[];
    value: string;
    onChange: (memberId: string) => void;
}) {
    const [search, setSearch] = useState("");
    const [isOpen, setIsOpen] = useState(false);
    const [activeIndex, setActiveIndex] = useState(0);

    useEffect(() => {
        const selected = members.find((member) => String(member.id) === value);
        if (selected) setSearch(selected.name);
    }, [members, value]);

    const normalizedSearch = search.trim().toLowerCase();
    const matches = members
        .filter((member) => {
            if (!normalizedSearch) return true;
            return [member.name, member.email, member.user_number]
                .filter(Boolean)
                .some((field) =>
                    String(field).toLowerCase().includes(normalizedSearch),
                );
        })
        .slice(0, 10);

    const selectMember = (member: any) => {
        onChange(String(member.id));
        setSearch(member.name);
        setIsOpen(false);
    };

    return (
        <div className="flex items-center gap-2">
            <label
                htmlFor="report-member"
                className="text-xs font-medium text-gray-500"
            >
                Member
            </label>
            <div className="relative w-56">
                <input
                    id="report-member"
                    type="search"
                    value={search}
                    onChange={(event) => {
                        setSearch(event.target.value);
                        setActiveIndex(0);
                        setIsOpen(true);
                        if (value) onChange("");
                    }}
                    onFocus={() => setIsOpen(true)}
                    onBlur={() => setTimeout(() => setIsOpen(false), 150)}
                    onKeyDown={(event) => {
                        if (event.key === "Escape") setIsOpen(false);
                        if (
                            event.key === "ArrowDown" ||
                            event.key === "ArrowUp"
                        ) {
                            event.preventDefault();
                            setIsOpen(true);
                            setActiveIndex((index) =>
                                Math.max(
                                    0,
                                    Math.min(
                                        matches.length - 1,
                                        index +
                                            (event.key === "ArrowDown"
                                                ? 1
                                                : -1),
                                    ),
                                ),
                            );
                        }
                        if (
                            event.key === "Enter" &&
                            isOpen &&
                            matches[activeIndex]
                        ) {
                            event.preventDefault();
                            selectMember(matches[activeIndex]);
                        }
                    }}
                    placeholder="Search members..."
                    autoComplete="off"
                    className={`${selectCls} w-full pr-8`}
                    role="combobox"
                    aria-expanded={isOpen}
                    aria-controls="report-member-options"
                    aria-autocomplete="list"
                    aria-activedescendant={
                        isOpen && matches[activeIndex]
                            ? `report-member-option-${matches[activeIndex].id}`
                            : undefined
                    }
                />
                {search && (
                    <button
                        type="button"
                        onMouseDown={(event) => event.preventDefault()}
                        onClick={() => {
                            setSearch("");
                            onChange("");
                            setIsOpen(false);
                        }}
                        className="absolute inset-y-0 right-0 px-2 text-gray-400 hover:text-gray-700"
                        aria-label="Clear member selection"
                    >
                        <i className="fas fa-times text-xs"></i>
                    </button>
                )}
                {isOpen && (
                    <div
                        id="report-member-options"
                        role="listbox"
                        aria-label="Members"
                        className="absolute z-20 mt-1 max-h-60 w-full overflow-y-auto rounded-lg border border-gray-300 bg-white py-1 shadow-lg"
                    >
                        {matches.length > 0 ? (
                            matches.map((member, index) => (
                                <button
                                    type="button"
                                    key={member.id}
                                    id={`report-member-option-${member.id}`}
                                    role="option"
                                    tabIndex={-1}
                                    aria-selected={index === activeIndex}
                                    onMouseEnter={() => setActiveIndex(index)}
                                    onMouseDown={(event) =>
                                        event.preventDefault()
                                    }
                                    onClick={() => selectMember(member)}
                                    className={`block w-full px-3 py-2 text-left text-sm hover:bg-blue-50 ${index === activeIndex ? "bg-blue-50 text-blue-700" : "text-gray-700"}`}
                                >
                                    <span className="block truncate">
                                        {member.name}
                                    </span>
                                    {(member.email || member.user_number) && (
                                        <span className="block truncate text-xs text-gray-400">
                                            {member.email || member.user_number}
                                        </span>
                                    )}
                                </button>
                            ))
                        ) : (
                            <p className="px-3 py-2 text-sm text-gray-500">
                                No members found.
                            </p>
                        )}
                    </div>
                )}
            </div>
            {!value && !search && (
                <button
                    type="button"
                    onClick={() => setIsOpen(true)}
                    className="text-xs text-gray-500 hover:text-blue-600"
                >
                    All Members
                </button>
            )}
        </div>
    );
}

export default function ReportsPage() {
    const { user } = useAuth();
    const { addToast } = useToast();
    const permissions = user?.permissions ?? [];
    const branchLocked = !permissions.includes("branches.bypass");
    const reports = REPORTS.filter(
        (report) =>
            !report.permission || permissions.includes(report.permission),
    );
    const [reportKey, setReportKey] = useState("financial");
    const report = reports.find((item) => item.key === reportKey) ?? reports[0];
    const [dates, setDates] = useState({ from: "", to: "" });
    const [branch, setBranch] = useState("");
    const [member, setMember] = useState("");
    const [loanStatus, setLoanStatus] = useState("all");
    const [category, setCategory] = useState("");
    const [activity, setActivity] = useState("all");
    const [sort, setSort] = useState("date");
    const sortableReportField = (key: string) => {
        if (report.key === "member_activity") return ({ transaction_number: "reference", member_name: "member", transaction_type: "type", amount: "amount", transaction_date: "date" } as Record<string, string>)[key];
        if (report.key === "expenses") return ({ expense_date: "date", category: "category", description: "description", amount: "amount" } as Record<string, string>)[key];
        return undefined;
    };
    const handleColumnSort = (field: string) => {
        setDirection(sort === field && direction === "asc" ? "desc" : "asc");
        setSort(field);
        setPage(1);
    };
    const [direction, setDirection] = useState("desc");
    const [accountType, setAccountType] = useState("");
    const [page, setPage] = useState(1);
    const [exporting, setExporting] = useState<"excel" | "pdf" | null>(null);
    const [refresh, setRefresh] = useState(0);
    const validDates = !dates.from || !dates.to || dates.from <= dates.to;
    const hasMember = ["member_savings", "member_activity"].includes(
        report.key,
    );
    const params = {
        ...dates,
        all_time: !dates.from ? 1 : undefined,
        branch_id: branch || undefined,
        ...(hasMember ? { member_id: member || undefined } : {}),
        ...(report.key === "loans" ? { filter: loanStatus } : {}),
        ...(report.key === "expenses"
            ? { category: category || undefined }
            : {}),
        ...(report.key === "expenses" ? { sort_by: sort, sort_direction: direction } : {}),
        ...(report.key === "member_activity"
            ? {
                  activity_type: activity,
                  sort_by: sort,
                  sort_direction: direction,
              }
            : {}),
        ...(report.key === "interest"
            ? { account_type: accountType || undefined }
            : {}),
    };
    useEffect(() => {
        setPage(1);
    }, [
        report.key,
        dates.from,
        dates.to,
        branch,
        member,
        loanStatus,
        category,
        activity,
        sort,
        direction,
        accountType,
    ]);
    const options = useQuery({
        queryKey: ["reports-options", branch],
        queryFn: () =>
            api
                .get("/api/reports/options", {
                    params: { branch_id: branch || undefined },
                })
                .then((response) => response.data),
    });
    const query = useQuery({
        queryKey: ["report-workspace", report.key, params, page, refresh],
        queryFn: () =>
            api
                .get(`/api/reports/${report.endpoint}`, {
                    params: { ...params, page },
                })
                .then((response) => response.data),
        enabled: validDates && report.key !== "interest",
    });
    const data = query.data;
    const download = async (format: "excel" | "pdf") => {
        if (exporting || !validDates) return;
        setExporting(format);
        try {
            const response = await api.post(
                "/api/reports/export",
                { ...params, report_type: report.exportType, format },
                { responseType: "blob" },
            );
            const disposition = response.headers["content-disposition"] ?? "";
            const filename =
                disposition.match(/filename="?([^";]+)"?/i)?.[1] ??
                `${report.exportType}-${dates.to}.${format === "excel" ? "xlsx" : "pdf"}`;
            const url = URL.createObjectURL(response.data);
            const anchor = document.createElement("a");
            anchor.href = url;
            anchor.download = filename;
            document.body.appendChild(anchor);
            anchor.click();
            anchor.remove();
            window.setTimeout(() => URL.revokeObjectURL(url), 1000);
            addToast("Report downloaded", "success");
        } catch (error: any) {
            let message =
                error.response?.data?.message ??
                "Could not export this report. Please try again.";
            if (error.response?.data instanceof Blob) {
                try {
                    message =
                        JSON.parse(await error.response.data.text()).message ??
                        message;
                } catch {
                    /* Keep readable fallback. */
                }
            }
            addToast(message, "error");
        } finally {
            setExporting(null);
        }
    };
    const changeReport = (key: string) => {
        setReportKey(key);
        setPage(1);
    };
    const branchName =
        options.data?.branches?.find((item: any) => String(item.id) === branch)
            ?.name ??
        (branchLocked && options.data?.branches?.length === 1
            ? options.data.branches[0].name
            : "All permitted branches");
    return (
        <div className="space-y-5 p-4 sm:p-6">
            <PageHeader eyebrow="Finance & operations" title="Reports" description="Choose a report, review the figures, and download a copy." icon="fas fa-chart-column" />
            <div className="grid items-start gap-5 lg:grid-cols-[220px_minmax(0,1fr)]">
                <nav
                    aria-label="Finance reports"
                    className="rounded-xl border border-emerald-100 bg-white p-3 lg:sticky lg:top-4"
                >
                    <div className="lg:hidden">
                        <Field title="Report">
                            <select
                                className={selectCls}
                                value={report.key}
                                onChange={(event) =>
                                    changeReport(event.target.value)
                                }
                            >
                                {GROUPS.map((group) => (
                                    <optgroup key={group} label={group}>
                                        {reports
                                            .filter(
                                                (item) => item.group === group,
                                            )
                                            .map((item) => (
                                                <option
                                                    key={item.key}
                                                    value={item.key}
                                                >
                                                    {item.title}
                                                </option>
                                            ))}
                                    </optgroup>
                                ))}
                            </select>
                        </Field>
                    </div>
                    <div className="hidden space-y-5 lg:block">
                        {GROUPS.map((group) => (
                            <div key={group}>
                                <p className="mb-2 px-3 text-[10px] font-semibold uppercase tracking-wider text-gray-400">
                                    {group}
                                </p>
                                <div className="space-y-1">
                                    {reports
                                        .filter((item) => item.group === group)
                                        .map((item) => (
                                            <button
                                                key={item.key}
                                                type="button"
                                                onClick={() =>
                                                    changeReport(item.key)
                                                }
                                                aria-current={
                                                    report.key === item.key
                                                        ? "page"
                                                        : undefined
                                                }
                                                className={`flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-left text-sm transition ${report.key === item.key ? "bg-emerald-50 font-semibold text-emerald-800" : "text-gray-600 hover:bg-gray-50"}`}
                                            >
                                                <i
                                                    aria-hidden="true"
                                                    className={`fas ${item.icon} w-4 text-center`}
                                                />
                                                <span>{item.title}</span>
                                            </button>
                                        ))}
                                </div>
                            </div>
                        ))}
                    </div>
                </nav>
                <section
                    aria-label={report.title}
                    className="min-w-0 space-y-4"
                >
                    <div className="rounded-xl border border-emerald-100 bg-white">
                        <div className="flex flex-wrap items-start justify-between gap-4 border-b border-gray-100 p-5">
                            <div>
                                <p className="text-xs font-medium text-emerald-700">
                                    {report.group}
                                </p>
                                <h2 className="mt-1 text-xl font-semibold text-gray-900">
                                    {report.title}
                                </h2>
                                <p className="mt-1 max-w-xl text-sm text-gray-500">
                                    {report.description}
                                </p>
                            </div>
                            <div className="flex flex-wrap items-center gap-2">
                                <button
                                    type="button"
                                    onClick={() => {
                                        setRefresh((value) => value + 1);
                                    }}
                                    aria-label="Refresh report"
                                    className="rounded-lg border border-gray-200 px-3 py-2 text-sm text-gray-600 hover:bg-gray-50"
                                >
                                    <i
                                        aria-hidden="true"
                                        className="fas fa-rotate-right"
                                    />
                                </button>
                                {permissions.includes("reports.export") && (
                                    <>
                                        <button
                                            type="button"
                                            disabled={
                                                !!exporting || !validDates
                                            }
                                            onClick={() => download("excel")}
                                            className="rounded-lg bg-emerald-700 px-3 py-2 text-sm font-medium text-white hover:bg-emerald-800 disabled:opacity-50"
                                        >
                                            <i
                                                aria-hidden="true"
                                                className="fas fa-file-excel mr-2"
                                            />
                                            {exporting === "excel"
                                                ? "Preparing..."
                                                : "Excel .xlsx"}
                                        </button>
                                        <button
                                            type="button"
                                            disabled={
                                                !!exporting || !validDates
                                            }
                                            onClick={() => download("pdf")}
                                            className="rounded-lg border border-gray-200 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50"
                                        >
                                            <i
                                                aria-hidden="true"
                                                className="fas fa-file-pdf mr-2"
                                            />
                                            {exporting === "pdf"
                                                ? "Preparing..."
                                                : "PDF"}
                                        </button>
                                    </>
                                )}
                            </div>
                        </div>
                        <div className="flex flex-wrap items-end gap-3 p-5">
                            <DateRangeFilter from={dates.from} to={dates.to}
                                onFromChange={from => setDates(value => ({ ...value, from }))}
                                onToChange={to => setDates(value => ({ ...value, to }))}
                                onRangeChange={(from, to) => setDates({ from, to })} />
                            <Field title="Branch">
                                <select
                                    className={selectCls}
                                    value={branch}
                                    disabled={options.isPending || branchLocked}
                                    onChange={(event) => {
                                        setBranch(event.target.value);
                                        setMember("");
                                        setCategory("");
                                    }}
                                >
                                    <option value="">
                                        {branchLocked &&
                                        options.data?.branches?.length === 1
                                            ? options.data.branches[0].name
                                            : "All permitted branches"}
                                    </option>
                                    {(!branchLocked
                                        ? (options.data?.branches ?? [])
                                        : []
                                    ).map((item: any) => (
                                        <option key={item.id} value={item.id}>
                                            {item.name}
                                        </option>
                                    ))}
                                </select>
                            </Field>
                            {hasMember && (
                                <MemberSearch
                                    key={branch}
                                    members={options.data?.members ?? []}
                                    value={member}
                                    onChange={setMember}
                                />
                            )}
                            {report.key === "loans" && (
                                <Field title="Loan status">
                                    <select
                                        className={selectCls}
                                        value={loanStatus}
                                        onChange={(event) =>
                                            setLoanStatus(event.target.value)
                                        }
                                    >
                                        {[
                                            "all",
                                            "active",
                                            "completed",
                                            "overdue",
                                        ].map((value) => (
                                            <option key={value} value={value}>
                                                {label(value)}
                                            </option>
                                        ))}
                                    </select>
                                </Field>
                            )}
                            {report.key === "expenses" && (
                                <Field title="Category">
                                    <select
                                        className={selectCls}
                                        value={category}
                                        onChange={(event) =>
                                            setCategory(event.target.value)
                                        }
                                    >
                                        <option value="">All categories</option>
                                        {(
                                            options.data?.expense_categories ??
                                            []
                                        ).map((value: string) => (
                                            <option key={value} value={value}>
                                                {value}
                                            </option>
                                        ))}
                                    </select>
                                </Field>
                            )}
                            {report.key === "interest" && (
                                <Field title="Account">
                                    <select
                                        className={selectCls}
                                        value={accountType}
                                        onChange={(event) =>
                                            setAccountType(event.target.value)
                                        }
                                    >
                                        <option value="">All accounts</option>
                                        <option value="savings">Savings</option>
                                        <option value="share-capital">
                                            Share capital
                                        </option>
                                    </select>
                                </Field>
                            )}
                            {report.key === "member_activity" && (
                                <>
                                    <Field title="Transaction type">
                                        <select
                                            className={selectCls}
                                            value={activity}
                                            onChange={(event) =>
                                                setActivity(event.target.value)
                                            }
                                        >
                                            {[
                                                "all",
                                                "deposit",
                                                "withdrawal",
                                                "loan_disbursement",
                                                "loan_payment",
                                                "interest",
                                                "fee",
                                            ].map((value) => (
                                                <option
                                                    key={value}
                                                    value={value}
                                                >
                                                    {label(value)}
                                                </option>
                                            ))}
                                        </select>
                                    </Field>
                                </>
                            )}
                        </div>
                        <div className="flex flex-wrap justify-between gap-2 border-t border-gray-100 px-5 py-3 text-xs text-gray-500">
                            <span>
                                {branchName} / {!dates.from && !dates.to ? "All time" : `${dates.from ? date(dates.from) : "Beginning"} - ${dates.to ? date(dates.to) : "Present"}`}
                            </span>
                            <span>Downloads include all matching records</span>
                        </div>
                    </div>
                    {!validDates && (
                        <p
                            role="alert"
                            className="rounded-xl bg-red-50 p-4 text-sm text-red-700"
                        >
                            Choose a valid start and end date. The end must be
                            on or after the start.
                        </p>
                    )}
                    {options.isError && (
                        <p role="alert" className="text-sm text-red-700">
                            Could not load filter choices.{" "}
                            <button
                                className="underline"
                                onClick={() => options.refetch()}
                            >
                                Retry
                            </button>
                        </p>
                    )}
                    {report.note && (
                        <p className="rounded-xl border border-amber-100 bg-amber-50/60 p-4 text-xs leading-relaxed text-amber-900">
                            <i
                                aria-hidden="true"
                                className="fas fa-circle-info mr-2"
                            />
                            {report.note}
                        </p>
                    )}
                    <div aria-live="polite" className="text-sm text-gray-500">
                        {exporting ? "Preparing your download..." : ""}
                    </div>
                    {validDates && report.key === "interest" ? (
                        <InterestDisbursementsReport
                            branchId={branch}
                            from={dates.from}
                            to={dates.to}
                            accountType={accountType}
                            refreshKey={refresh}
                        />
                    ) : (
                        validDates &&
                        (query.isPending ? (
                            <div
                                role="status"
                                className="rounded-xl border border-gray-100 bg-white p-10 text-center text-sm text-gray-500"
                            >
                                Loading report...
                            </div>
                        ) : query.isError ? (
                            <div
                                role="alert"
                                className="rounded-xl border border-red-100 bg-red-50 p-5 text-sm text-red-700"
                            >
                                Could not load this report.{" "}
                                <button
                                    onClick={() => query.refetch()}
                                    className="font-medium underline"
                                >
                                    Retry
                                </button>
                            </div>
                        ) : report.key === "financial" ? (
                            <FinancialSummary data={data} />
                        ) : report.key === "balance_sheet" ? (
                            <BalanceSheet data={data} />
                        ) : (
                            <div className="overflow-hidden rounded-xl border border-gray-200 bg-white">
                                <div className="flex flex-wrap items-center justify-between gap-2 border-b border-gray-100 px-5 py-4">
                                    <p className="text-sm text-gray-600">
                                        <strong className="text-gray-900">
                                            {data?.meta?.total ?? 0}
                                        </strong>{" "}
                                        matching records
                                    </p>
                                    {data?.total !== undefined && (
                                        <p className="text-sm text-gray-600">
                                            {report.key === "expenses"
                                                ? "Total expenses"
                                                : "Net transaction total"}
                                            :{" "}
                                            <strong className="text-gray-900">
                                                {money(data.total)}
                                            </strong>
                                        </p>
                                    )}
                                </div>
                                <div className="overflow-x-auto">
                                    <table className="w-full text-left text-sm">
                                        <thead className="bg-gray-50 text-xs text-gray-500">
                                            <tr>
                                                {report.columns?.map(
                                                    (column) => (
                                                        sortableReportField(column.key) ? <SortableHeader
                                                            key={column.key} field={sortableReportField(column.key)!} sort={sort} direction={direction} onSort={handleColumnSort}
                                                            className={`whitespace-nowrap px-5 py-3 ${column.type === "money" ? "text-right" : ""}`}>
                                                            {column.label}
                                                        </SortableHeader> : <th
                                                            key={column.key}
                                                            scope="col"
                                                            className={`whitespace-nowrap px-5 py-3 font-medium ${column.type === "money" || column.type === "percent" ? "text-right" : ""}`}
                                                        >
                                                            {column.label}
                                                        </th>
                                                    ),
                                                )}
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-gray-100">
                                            {!data?.data?.length ? (
                                                <tr>
                                                    <td
                                                        colSpan={
                                                            report.columns
                                                                ?.length
                                                        }
                                                        className="p-10 text-center text-gray-500"
                                                    >
                                                        No records match these
                                                        filters.
                                                    </td>
                                                </tr>
                                            ) : (
                                                data.data.map(
                                                    (
                                                        row: any,
                                                        index: number,
                                                    ) => (
                                                        <tr
                                                            key={
                                                                row.id ??
                                                                row.loan_number ??
                                                                index
                                                            }
                                                            className="hover:bg-gray-50/70"
                                                        >
                                                            {report.columns?.map(
                                                                (column) => (
                                                                    <td
                                                                        key={
                                                                            column.key
                                                                        }
                                                                        className={`px-5 py-3 ${column.type === "money" || column.type === "percent" ? "whitespace-nowrap text-right tabular-nums" : "min-w-32 text-gray-700"}`}
                                                                    >
                                                                        {column.type ===
                                                                        "money"
                                                                            ? money(
                                                                                  row[
                                                                                      column
                                                                                          .key
                                                                                  ],
                                                                              )
                                                                            : column.type ===
                                                                                "date"
                                                                              ? date(
                                                                                    row[
                                                                                        column
                                                                                            .key
                                                                                    ],
                                                                                )
                                                                              : column.type ===
                                                                                  "percent"
                                                                                ? `${Number(row[column.key]).toFixed(2)}%`
                                                                                : column.key ===
                                                                                    "transaction_type"
                                                                                  ? label(
                                                                                        row[
                                                                                            column
                                                                                                .key
                                                                                        ],
                                                                                    )
                                                                                  : (row[
                                                                                        column
                                                                                            .key
                                                                                    ] ??
                                                                                    "\u2014")}
                                                                    </td>
                                                                ),
                                                            )}
                                                        </tr>
                                                    ),
                                                )
                                            )}
                                        </tbody>
                                    </table>
                                </div>
                                {data?.meta && (
                                    <div className="border-t border-gray-100 p-4">
                                        <Pagination
                                            currentPage={page}
                                            lastPage={data.meta.last_page}
                                            total={data.meta.total}
                                            perPage={data.meta.per_page}
                                            onPageChange={setPage}
                                        />
                                    </div>
                                )}
                            </div>
                        ))
                    )}
                </section>
            </div>
        </div>
    );
}
const METRICS: [string, string][] = [
    ["total_deposits", "Deposits"],
    ["total_withdrawals", "Withdrawals"],
    ["total_loan_releases", "Loan releases"],
    ["total_loan_repayments", "Loan repayments"],
    ["total_interest_credits", "Interest credits"],
    ["total_interest_earned", "Estimated loan interest collected"],
    ["projected_interest", "Projected loan interest"],
    ["total_fees", "Fees"],
    ["total_expenses", "Operating expenses"],
    ["net", "Net movement"],
];
function FinancialSummary({ data }: { data: any }) {
    const series = data?.monthly_series ?? [];
    return (
        <div className="space-y-4">
            <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                {[
                    ["net", "Net movement"],
                    ["total_deposits", "Deposits"],
                    ["total_loan_repayments", "Loan repayments"],
                    ["total_expenses", "Operating expenses"],
                ].map(([key, text]) => (
                    <div
                        key={key}
                        className="rounded-xl border border-emerald-100 bg-white p-5"
                    >
                        <p className="text-xs font-medium text-gray-500">
                            {text}
                        </p>
                        <p className="mt-2 text-xl font-semibold tabular-nums text-gray-900">
                            {money(data?.[key])}
                        </p>
                    </div>
                ))}
            </div>
            <div className="grid gap-4 xl:grid-cols-2">
                <div className="rounded-xl border border-gray-200 bg-white p-5">
                    <h3 className="mb-4 font-semibold text-gray-900">
                        Period totals
                    </h3>
                    <dl className="divide-y divide-gray-100">
                        {METRICS.map(([key, text]) => (
                            <div
                                key={key}
                                className="flex justify-between gap-4 py-2.5 text-sm"
                            >
                                <dt className="text-gray-600">{text}</dt>
                                <dd className="font-medium tabular-nums text-gray-900">
                                    {money(data?.[key])}
                                </dd>
                            </div>
                        ))}
                    </dl>
                </div>
                <div className="min-w-0 rounded-xl border border-gray-200 bg-white p-5">
                    <h3 className="font-semibold text-gray-900">
                        Monthly trend
                    </h3>
                    <p className="mt-1 text-xs text-gray-500">
                        Trailing six months through the selected end month;
                        separate from period totals.
                    </p>
                    <div className="mt-5 h-72">
                        <Bar
                            data={{
                                labels: series.map((row: any) => row.label),
                                datasets: [
                                    {
                                        label: "Deposits",
                                        data: series.map(
                                            (row: any) => row.deposits,
                                        ),
                                        backgroundColor: "#059669",
                                    },
                                    {
                                        label: "Loan repayments",
                                        data: series.map(
                                            (row: any) => row.loan_repayments,
                                        ),
                                        backgroundColor: "#38bdf8",
                                    },
                                    {
                                        label: "Expenses",
                                        data: series.map(
                                            (row: any) => row.expenses,
                                        ),
                                        backgroundColor: "#f59e0b",
                                    },
                                ],
                            }}
                            options={{
                                responsive: true,
                                maintainAspectRatio: false,
                                plugins: { legend: { position: "bottom" } },
                            }}
                            role="img"
                            aria-label="Monthly deposits, loan repayments and expenses; figures available in the monthly breakdown table below"
                        />
                    </div>
                </div>
            </div>
            <div className="overflow-hidden rounded-xl border border-gray-200 bg-white">
                <h3 className="p-5 font-semibold text-gray-900">
                    Monthly breakdown / trailing six months
                </h3>
                <div className="overflow-x-auto">
                    <table className="w-full text-sm">
                        <thead className="bg-gray-50 text-xs text-gray-500">
                            <tr>
                                {[
                                    "Month",
                                    "Deposits",
                                    "Interest credits",
                                    "Loan repayments",
                                    "Loan releases",
                                    "Withdrawals",
                                    "Fees",
                                    "Expenses",
                                    "Net",
                                ].map((text) => (
                                    <th
                                        key={text}
                                        className="whitespace-nowrap px-4 py-3 text-right first:text-left"
                                    >
                                        {text}
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-100">
                            {series.map((row: any) => (
                                <tr key={row.label}>
                                    <th
                                        scope="row"
                                        className="whitespace-nowrap px-4 py-3 text-left font-medium"
                                    >
                                        {row.label}
                                    </th>
                                    {[
                                        "deposits",
                                        "interest_credits",
                                        "loan_repayments",
                                        "loan_releases",
                                        "withdrawals",
                                        "fees",
                                        "expenses",
                                        "net",
                                    ].map((key) => (
                                        <td
                                            key={key}
                                            className="whitespace-nowrap px-4 py-3 text-right tabular-nums"
                                        >
                                            {money(row[key])}
                                        </td>
                                    ))}
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    );
}
function BalanceSheet({ data }: { data: any }) {
    return (
        <div className="space-y-4">
            <p className="text-sm text-gray-500">
                Financial position as of {date(data?.as_of)}
            </p>
            <div className="grid gap-4 xl:grid-cols-3">
                {["assets", "liabilities", "equity"].map((section) => (
                    <div
                        key={section}
                        className="rounded-xl border border-gray-200 bg-white p-5"
                    >
                        <h3 className="mb-3 font-semibold capitalize text-gray-900">
                            {section}
                        </h3>
                        <dl className="space-y-3">
                            {(data?.[section] ?? []).map((row: any) => (
                                <div
                                    key={row.label}
                                    className="flex justify-between gap-3 text-sm"
                                >
                                    <dt className="text-gray-600">
                                        {row.label}
                                    </dt>
                                    <dd className="whitespace-nowrap tabular-nums">
                                        {money(row.amount)}
                                    </dd>
                                </div>
                            ))}
                            <div className="flex justify-between gap-3 border-t border-gray-100 pt-3 text-sm font-semibold">
                                <dt>Total {section}</dt>
                                <dd className="whitespace-nowrap tabular-nums">
                                    {money(data?.totals?.[section])}
                                </dd>
                            </div>
                        </dl>
                    </div>
                ))}
            </div>
            <p className="text-right text-sm font-semibold text-gray-700">
                Total liabilities and equity:{" "}
                {money(data?.totals?.liabilities_and_equity)}
            </p>
        </div>
    );
}
