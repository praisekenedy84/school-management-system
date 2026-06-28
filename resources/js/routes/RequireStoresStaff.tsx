import type { ReactNode } from 'react';
import { RequireAnyPermission } from './RequireAnyPermission';

export const STOREKEEPER_CATALOG_PERMISSIONS = ['stores.manage_catalog'];
export const STOREKEEPER_REQUISITION_QUEUE_PERMISSIONS = [
    'stores.approve_requisitions',
    'stores.issue_requisitions',
    'stores.view_requisitions',
];
export const STOREKEEPER_PURCHASE_PERMISSIONS = ['stores.create_purchase_requests'];
export const STOREKEEPER_STOCK_PERMISSIONS = ['stores.view_stock'];
export const STOREKEEPER_MOVEMENTS_PERMISSIONS = ['stores.view_movements'];
export const KITCHEN_REQUISITION_PERMISSIONS = ['stores.create_requisitions'];
export const PROCUREMENT_PERMISSIONS = ['stores.approve_purchases'];
export const FULFILLMENT_PERMISSIONS = ['stores.fulfill_purchases'];

/** @deprecated Prefer permission constants + `RequireAnyPermission`. */
export const STOREKEEPER_ROLES = ['storekeeper', 'school_admin', 'tenant_admin'];
/** @deprecated Prefer permission constants + `RequireAnyPermission`. */
export const KITCHEN_STAFF_ROLES = ['kitchen_staff', 'storekeeper', 'school_admin', 'tenant_admin'];

export function RequireStorekeeper({ children }: { children: ReactNode }) {
    return (
        <RequireAnyPermission
            permissions={[
                ...STOREKEEPER_CATALOG_PERMISSIONS,
                ...STOREKEEPER_REQUISITION_QUEUE_PERMISSIONS,
                ...STOREKEEPER_PURCHASE_PERMISSIONS,
                ...STOREKEEPER_STOCK_PERMISSIONS,
                ...STOREKEEPER_MOVEMENTS_PERMISSIONS,
            ]}
        >
            {children}
        </RequireAnyPermission>
    );
}

export function RequireKitchenStaff({ children }: { children: ReactNode }) {
    return <RequireAnyPermission permissions={KITCHEN_REQUISITION_PERMISSIONS}>{children}</RequireAnyPermission>;
}

export function RequireStoreCatalog({ children }: { children: ReactNode }) {
    return <RequireAnyPermission permissions={STOREKEEPER_CATALOG_PERMISSIONS}>{children}</RequireAnyPermission>;
}

export function RequireStoreStock({ children }: { children: ReactNode }) {
    return <RequireAnyPermission permissions={STOREKEEPER_STOCK_PERMISSIONS}>{children}</RequireAnyPermission>;
}

export function RequireRequisitionQueue({ children }: { children: ReactNode }) {
    return <RequireAnyPermission permissions={STOREKEEPER_REQUISITION_QUEUE_PERMISSIONS}>{children}</RequireAnyPermission>;
}

export function RequirePurchaseRequests({ children }: { children: ReactNode }) {
    return <RequireAnyPermission permissions={STOREKEEPER_PURCHASE_PERMISSIONS}>{children}</RequireAnyPermission>;
}

export function RequireStockMovements({ children }: { children: ReactNode }) {
    return <RequireAnyPermission permissions={STOREKEEPER_MOVEMENTS_PERMISSIONS}>{children}</RequireAnyPermission>;
}

export function RequireProcurementStaff({ children }: { children: ReactNode }) {
    return <RequireAnyPermission permissions={PROCUREMENT_PERMISSIONS}>{children}</RequireAnyPermission>;
}

export function RequireFulfillmentStaff({ children }: { children: ReactNode }) {
    return <RequireAnyPermission permissions={FULFILLMENT_PERMISSIONS}>{children}</RequireAnyPermission>;
}
