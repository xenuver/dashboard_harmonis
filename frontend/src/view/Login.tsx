import { useState } from 'react';
import { useNavigate } from "react-router-dom";

import { api } from "../services/api";
import session from '../services/sessionManager';

import type { SubmitEvent } from 'react';

import { Button } from "@/components/ui/button"
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from "@/components/ui/card"
import { Input } from "@/components/ui/input"
import { FieldGroup, FieldLabel, Field } from "@/components/ui/field";

import type {SuccessLoginResponse} from '../../types/LoginResponse';
import { isAxiosError } from 'axios';

import "../styles/index.css";

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
          // setLoginMessageText(`anda login sebagai ${username.trim()}`);

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
        const status = e.status || e.response?.status;

        if (status === 401) {
          setLoginMessageText("username atau password salah");
        } else if (e.code === "ERR_NETWORK") {
          console.error(e);
          console.warn("Terjadi kesalahan dalam mendapatkan respon dari server");
          setLoginMessageText("Terjadi kesalahan dalam mendapatkan respon dari server");
        } else if (e.code === "ERR_BAD_RESPONSE") {
          setLoginMessageText("Server tidak merespon dengan format data yang tepat");
        } else if (e.code === "ECONNABORTED" || e.code === "ERR_CANCELED") {
          setLoginMessageText("Permintaan dibatalkan atau waktu habis");
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
      <div className="flex min-h-screen w-full items-center justify-center p-4">
        <Card className="w-full max-w-lg self-center -translate-y-12">
          <form onSubmit={handleLoginAttempt}>
            <CardHeader>
              <CardTitle>Masuk</CardTitle>
              <CardDescription className="mb-3">
                Masuk dengan nama pengguna anda untuk mengakses aplikasi ini
              </CardDescription>
            </CardHeader>
            <CardContent>
              <FieldGroup className="flex flex-col gap-4">
                <Field className="grid">
                  <FieldLabel htmlFor="username">Nama Pengguna</FieldLabel>
                  <Input type="text" name="username" required />
                </Field>
                <Field className="grid">
                  <FieldLabel htmlFor="password">Password</FieldLabel>
                  <Input type="password" name="password" required />
                </Field>
              </FieldGroup>
                <div className="text-red-600 mb-1 mt-1">
                  {loginMessageText}
                </div>
            </CardContent>
            <CardFooter className="flex-col gap-2">
              <Button type="submit" className="w-full h-10">Masuk</Button>
            </CardFooter>
          </form>
        </Card>
      </div>
    </>
  )
}

export default LoginScreen
