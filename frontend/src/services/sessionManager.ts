import type { UserInformation } from "../../types/UserInformation";

const localStorageTokenName: string = "session_token";
const localStorageUserIdentityName: string = "session_identity"; // suggested by ai, but there is better way which will be done later

let sessionToken = localStorage.getItem(localStorageTokenName);
let cachedIdentity = localStorage.getItem(localStorageUserIdentityName);

// auto update tokens if updated by other tabs
window.addEventListener("storage", function(e: StorageEvent) {
  if(e.key === localStorageTokenName) {
    sessionToken = localStorage.getItem(localStorageTokenName);
  }

  if(e.key === localStorageUserIdentityName) {
    cachedIdentity = localStorage.getItem(localStorageUserIdentityName);
  }
});

export default {
  getToken: function(): string | null {
    return sessionToken;
  },

  setToken: function(newToken: string): void {
    sessionToken = newToken;
    localStorage.setItem(localStorageTokenName, sessionToken);
  },

  getIdentity: function(): UserInformation | null {
    if(!cachedIdentity) {
      return null;
    }
    
    try {
      return JSON.parse(cachedIdentity);
    } catch(e) {
      console.error(e);
      console.warn("Failed to parse JSON");
      return null;
    }
  },

  setIdentity: function(identity: UserInformation): void {
    cachedIdentity = JSON.stringify(identity);
    localStorage.setItem(localStorageUserIdentityName, cachedIdentity);
  },

  deleteTokens: function(): void {
    localStorage.removeItem(localStorageTokenName);
    localStorage.removeItem(localStorageUserIdentityName);
    sessionToken = null;
    cachedIdentity = null;
  },

  logout: function(): void {
    this.deleteTokens();
    location.href = "/login";
  }
}