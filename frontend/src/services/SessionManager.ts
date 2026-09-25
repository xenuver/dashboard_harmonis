const localStorageTokenName: string = "auth_token";

let sessionToken = localStorage.getItem(localStorageTokenName);


export default {
  setToken: function setToken(newToken: string) {
    sessionToken = newToken;
    localStorage.setItem("auth_token", sessionToken);
  },

  getToken: function getToken() {
    return sessionToken;
  },

  logout: function logout(): void {
    localStorage.removeItem(localStorageTokenName);
    sessionToken = null;
  }
}