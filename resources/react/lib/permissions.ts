export function formatPermissionLabel(permission: string): string {
    const parts = permission.split('.');
    if (parts.length < 2) {
        return permission.replace(/[-_]/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
    }
    const resource = parts[0];
    const action = parts.slice(1).join(' ');
    return `${action.replace(/[-_]/g, ' ').replace(/\b\w/g, c => c.toUpperCase())} ${resource.replace(/[-_]/g, ' ').replace(/\b\w/g, c => c.toUpperCase())}`;
}