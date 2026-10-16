import type { UserInformation } from './UserInformation'

export interface UserSearchResponse {
    current_page: number,
    data: UserInformation[],
    last_page: number
    per_page: number,
    total: number
}
