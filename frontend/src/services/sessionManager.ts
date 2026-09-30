const localStorageTokenName: string = "session_token";
const localStorageUserIdentity: string = "session_identity"; // suggested by ai, but there is better way which will be done later

let sessionToken = localStorage.getItem(localStorageTokenName);
let cachedIdentity = localStorage.getItem(localStorageUserIdentity);

export default {
  getToken: function(): string | null {
    return sessionToken;
  },

  setToken: function(newToken: string): void {
    sessionToken = newToken;
    localStorage.setItem(localStorageTokenName, sessionToken);
  },

  getIdentity: function(): object | null {
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

  setIdentity: function(identity: object): void {
    cachedIdentity = JSON.stringify(identity);
    localStorage.setItem(localStorageUserIdentity, cachedIdentity);
  },

  logout: function(): void {
    localStorage.removeItem(localStorageTokenName);
    localStorage.removeItem(localStorageUserIdentity);
    sessionToken = null;
    cachedIdentity = null;
  }
}