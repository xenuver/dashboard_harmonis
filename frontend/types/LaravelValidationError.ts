export default interface LaravelValidationError {
    "message": string;
    "errors": Record<string, string[]>;
}