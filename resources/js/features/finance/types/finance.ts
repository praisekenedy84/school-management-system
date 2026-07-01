/** Mirrors App\Http\Resources\FeeStructureResource. */
export interface FeeStructure {
    id: string;
    school_id: string;
    academic_session_id: string;
    academic_session_name: string | null;
    class_id: string;
    class_name: string | null;
    fee_type: string;
    amount: number;
    is_mandatory: boolean;
    applicable_to: 'all' | 'day_only' | 'boarding_only';
    installment_allowed: boolean;
    installment_count: number | null;
    due_date: string | null;
    is_active: boolean;
    created_by: string | null;
    created_at: string | null;
}

/** Body for POST/PUT /api/v1/fee-structures (App\Http\Requests\Finance\FeeStructureRequest). */
export interface FeeStructureRequest {
    academic_session_id: string;
    class_id: string;
    fee_type: string;
    amount: number;
    is_mandatory: boolean;
    applicable_to: 'all' | 'day_only' | 'boarding_only';
    installment_allowed: boolean;
    installment_count?: number | null;
    due_date?: string | null;
    is_active: boolean;
}

/** One of the payment-method types the backend accepts (PaymentMethodRequest). */
export type PaymentMethodType = 'bank_transfer' | 'cash_deposit' | 'mobile_money' | 'cheque' | 'direct_cash';

/** Mirrors App\Http\Resources\PaymentMethodResource. */
export interface PaymentMethod {
    id: string;
    school_id: string;
    name: string;
    type: PaymentMethodType;
    bank_name: string | null;
    account_number: string | null;
    account_name: string | null;
    branch_code: string | null;
    swift_code: string | null;
    payment_instructions: string | null;
    is_active: boolean;
    created_at: string | null;
}

/** Body for POST/PUT /api/v1/payment-methods (App\Http\Requests\Finance\PaymentMethodRequest). */
export interface PaymentMethodRequest {
    name: string;
    type: PaymentMethodType;
    bank_name?: string | null;
    account_number?: string | null;
    account_name?: string | null;
    branch_code?: string | null;
    swift_code?: string | null;
    payment_instructions?: string | null;
    is_active: boolean;
}

/** One line of a payment slip's `allocation` JSON array. */
export interface AllocationLine {
    fee_type: string;
    amount: number;
    academic_session_id: string;
}

/** One line on a student's fee statement. */
export interface FeeStatementLine {
    fee_type: string;
    total_charged: string;
    total_paid: string;
    balance: string;
}

/** Mirrors GET /api/v1/students/{id}/fee-statement response. */
export interface FeeStatement {
    student_id: string;
    academic_session_id: string;
    academic_session_name: string | null;
    lines: FeeStatementLine[];
    totals: {
        total_charged: string;
        total_paid: string;
        balance: string;
    };
    pending_slips: Array<{
        id: string;
        slip_number: string;
        total_amount: string;
        status: string;
    }>;
}

/** One attachment entry in a payment slip's `slip_attachments` JSON array. */
export interface SlipAttachment {
    file_path: string;
    thumbnail_path?: string | null;
    file_name: string;
    size: number;
    mime: string;
    uploaded_at: string;
}

/** Lifecycle status a payment slip can be in (PaymentSlipResource `status`). */
export type PaymentSlipStatus =
    | 'pending'
    | 'under_review'
    | 'verified'
    | 'approved'
    | 'rejected'
    | 'disputed'
    | 'clarification_needed';

/** Mirrors App\Http\Resources\PaymentReceiptResource. */
export interface PaymentReceipt {
    id: string;
    school_id: string;
    payment_slip_id: string;
    receipt_number: string;
    amount_in_words: string | null;
    payment_details: Record<string, unknown> | null;
    qr_code_path: string | null;
    file_path: string | null;
    generated_by: string | null;
    generated_at: string | null;
}

/** Mirrors App\Http\Resources\PaymentSlipResource. */
export interface PaymentSlip {
    id: string;
    school_id: string;
    slip_number: string;
    student_id: string;
    student_name: string | null;
    submitted_by: string;
    payment_method_id: string | null;
    bank_name: string | null;
    branch_name: string | null;
    teller_number: string | null;
    transaction_reference: string | null;
    depositor_name: string;
    deposit_date: string | null;
    total_amount: number;
    currency: string;
    allocation: AllocationLine[];
    slip_attachments: SlipAttachment[];
    status: PaymentSlipStatus;
    verified_by: string | null;
    verified_at: string | null;
    verification_notes: string | null;
    rejected_by: string | null;
    rejected_at: string | null;
    rejection_category: string | null;
    rejection_reason: string | null;
    receipt_number: string | null;
    receipt_generated_at: string | null;
    receipt: PaymentReceipt | null;
    notes: string | null;
    created_at: string | null;
    updated_at: string | null;
}

/** Query params for GET /api/v1/payment-slips. */
export interface PaymentSlipQuery {
    status?: PaymentSlipStatus;
    student_id?: string;
    per_page?: number;
    page?: number;
}

/**
 * Body for POST /api/v1/payment-slips (App\Http\Requests\Finance\SubmitPaymentSlipRequest).
 * Sent as multipart/form-data — `slip_attachments` are real File objects, not
 * part of this JSON-shaped type; see useSubmitPaymentSlip's FormData builder.
 */
export interface SubmitPaymentSlipRequest {
    student_id: string;
    payment_method_id?: string | null;
    bank_name?: string | null;
    branch_name?: string | null;
    teller_number?: string | null;
    transaction_reference?: string | null;
    depositor_name: string;
    deposit_date: string;
    total_amount: number;
    currency?: string;
    allocation: AllocationLine[];
    notes?: string | null;
}

/** Category for POST /api/v1/payment-slips/{id}/reject (RejectPaymentSlipRequest). */
export type RejectionCategory = 'incorrect_amount' | 'unclear_image' | 'wrong_details' | 'duplicate' | 'other';

/** Body for POST /api/v1/payment-slips/{id}/verify. */
export interface VerifyPaymentSlipRequest {
    verification_notes: string;
}

/** Body for POST /api/v1/payment-slips/{id}/reject. */
export interface RejectPaymentSlipRequest {
    rejection_category: RejectionCategory;
    rejection_reason: string;
}
