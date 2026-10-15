import type ReportValidationErrorItem from "./Models/ReportValidationErrorItem";

export default interface ReportUploadValidationErrorResponse {
    message: string;
    xlsxValidationErrors: Record<string, ReportValidationErrorItem[]>;
}