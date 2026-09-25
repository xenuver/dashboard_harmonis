import React from 'react';
import type {SuccessLoginResponse} from '../../types/LoginResponse';
import session from '../services/SessionManager';
import axios from 'axios';

export function LoginScreen() {
  const backendProtocol = location.protocol;
  const backendHostname = location.hostname;
  const backendPort = 8000;

  async function handleLoginAttempt(event: React.SubmitEvent) {
    event.preventDefault();

    const form = new FormData(event.target);
    // get the field that you want
    const username = form.get("username");
    const password = form.get("password");

    if(typeof username !== "string" || (typeof username === "string" && username.trim().length === 0)) {
      window.alert("Username tidak boleh kosong");
      return;
    }

    if(typeof password !== "string" || (typeof password === "string" && password.length === 0)) {
      window.alert("Password tidak boleh kosong");
      return;
    }

    const loginInformation = await axios.postForm(`${backendProtocol}//${backendHostname}:${backendPort}/api/login`, {
      username: username.trim(),
      password: password,
    }, {
      responseType: "json"
    });

    if(loginInformation.status === 200) {
      window.alert(`anda login sebagai ${username.trim()}`);

      try {
        const la = await loginInformation.data as SuccessLoginResponse;

        session.setToken(la.token);
      } catch(e) {
        console.error(e);
        window.alert("unexpected login login token value");
      }
    } else if(loginInformation.status === 401) {
      window.alert(`username atau password salah`);
    } else {
      window.alert("Terjadi kesalahan dalam membuat permintaan login");
    }
  }

  return (
    <>
      <h1>Harmonis Dashboard</h1>
      <form onSubmit={handleLoginAttempt}>
        <div>Username:&nbsp;</div><input type="text" name="username" minLength={1}></input>
        <div>Password:&nbsp;</div><input type="password" name="password" minLength={1}></input>
        <button type="submit">masuk</button>
      </form>
    </>
  )
}

export default LoginScreen
