import type {UserInformation} from './UserInformation';

export interface SuccessLoginResponse {
    user: UserInformation
    token: string
}
