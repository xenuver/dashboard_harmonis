import { useState } from 'react';
import { useNavigate } from "react-router-dom";

import { api } from "../services/api";
import session from '../services/sessionManager';

import type { SubmitEvent } from 'react';

import type {SuccessLoginResponse} from '../../types/LoginResponse';
import { isAxiosError } from 'axios';

export function LoginScreen() {
  const [loginMessageText, setLoginMessageText] = useState('');
  const navigate = useNavigate();

  async function handleLoginAttempt(event: SubmitEvent) {
    event.preventDefault();

    setLoginMessageText('');

    const form = new FormData(event.target);
    // get the field that you want
    const username = form.get("username");
    const password = form.get("password");

    if(typeof username !== "string" || (typeof username === "string" && username.trim().length < 1)) {
      setLoginMessageText("Username tidak boleh kosong");
      return;
    }

    if(typeof password !== "string" || (typeof password === "string" && password.length < 1)) {
      setLoginMessageText("Password tidak boleh kosong");
      return;
    }

    try {
      const loginInformation = await api.postForm(`/api/login`, {
        username: username.trim(),
        password: password,
      }, {
        responseType: "json",
      });

      if(loginInformation.status === 200) {
        try {
          const la = await loginInformation.data as SuccessLoginResponse;
          setLoginMessageText(`anda login sebagai ${username.trim()}`);

          session.setToken(la.token);
          session.setIdentity(la.user);

          navigate("/dashboard");
        } catch(e) {
          console.error(e);
          setLoginMessageText("Respon login berhasil memiliki format yang tidak tepat");
        }
      }
    } catch(e) {
      if(isAxiosError(e)) {
        if(e.status === 401) {
          setLoginMessageText(`username atau password salah`);
        } else if(e.code == "ERR_NETWORK") {
          console.error(e);
          console.warn("Terjadi kesalahan dalam mendapatkan respon dari server");
          setLoginMessageText("Terjadi kesalahan dalam mendapatkan respon dari server");
        } else if (e.code === "ERR_BAD_RESPONSE") {
          setLoginMessageText("Server tidak merespon dengan format data yang tepat");
        } else if(e.code === "ECONNABORTED") {
          setLoginMessageText("Permintaan dibatalkan");
        } else {
          console.error(e);
          console.warn("Terjadi kesalahan dalam membuat permintaan login");
          setLoginMessageText("Terjadi kesalahan dalam membuat permintaan login");
        }
      } else {
        console.log(e);
        console.warn("Terjadi kesalahan dalam membuat koneksi");
      }
    }
  }

  return (
    <>
      <h1>Harmonis Dashboard</h1>
      <form onSubmit={handleLoginAttempt}>
        <div>Username:&nbsp;</div><input type="text" name="username" minLength={1}></input>
        <div>Password:&nbsp;</div><input type="password" name="password" minLength={1}></input>
        <div id="login-message">{loginMessageText}</div>
        <button type="submit">masuk</button>
      </form>
    </>
  )
}

export default LoginScreen
