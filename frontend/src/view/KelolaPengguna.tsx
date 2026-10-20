import { useState as reactUseState, useEffect, useRef } from "react";
import { isAxiosError, isCancel as axiosIsCancel } from "axios";
import { api } from "../services/api";
import { ArrowLeft, ArrowRight, Search } from "lucide-react";

import { Button, } from "@/components/ui/button";
import { Card, CardContent } from "@/components/ui/card";
import { Field, FieldGroup, FieldLabel } from "@/components/ui/field";
import { Input } from "@/components/ui/input";
import { NativeSelect, NativeSelectOption } from "@/components/ui/native-select"
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { Skeleton } from "@/components/ui/skeleton";
import { NoConnectionCard } from "@/components/errorcards/NoConnectionCard";
import { UnknownErrorCard } from "@/components/errorcards/UnknownErrorCard";
import { NoDataFoundCard } from "@/components/errorcards/NoDataFoundCard";
import { AddNewUserDialogFlow } from "@/components/manageusers/AddNewUserDialogFlow";

import type { UserSearchResponse } from "../../types/UserSearchResponse";
import type { UserInformation } from "types/UserInformation";
import { DeleteUserDialogFlow } from "@/components/manageusers/DeleteUserDialogFlow";
import { EditUserDialogFlow } from "@/components/manageusers/EditUserDialogFlow";

import sessionManager from "@/services/sessionManager";

const userRoles = [
  {
    label: "Owner",
    value: "owner"
  },
  {
    label: "Staf IT",
    value: "it_staff",
  }
];

const mapUserRole: Record<string, string> = userRoles.reduce((acc, role) => {
  acc[role.value] = role.label;
  return acc;
}, {} as Record<string, string>);

const ERROR_CODE_SEARCH_NO_ERROR = 0;
const ERROR_CODE_SEARCH_NO_DATA = 1;
const ERROR_CODE_SEARCH_CONNECTION_ERROR = 2;
const ERROR_CODE_SEARCH_UNKNOWN_ERROR = 3;

export function KelolaPengguna() {
  const searchHasMounted = useRef(false);

  const [roleSaatIni, setRoleSaatIni] = reactUseState<string | undefined>(undefined);
  const [kataKunciPengguna, setKataKunciPengguna] = reactUseState < string | undefined > (undefined);
  const [usersPerPage, setUsersPerPage] = reactUseState(10);
  const [daftarPengguna, setDaftarPengguna] = reactUseState < UserInformation[] > ([]);

  const [currentPage, setCurrentPage] = reactUseState < number > (1);
  const [totalUsers, setTotalUsers] = reactUseState < number > (0);
  const [maxPage, setMaxPage] = reactUseState < number > (1);

  const [isLoaded, setIsLoaded] = reactUseState < boolean > (false);
  const [errorCode, setErrorCode] = reactUseState < number > (ERROR_CODE_SEARCH_NO_ERROR);
  const [retryNowTrigger, shouldRetryNow] = reactUseState < boolean > (false);
  const [resetSearchTrigger, shouldResetSearchNow] = reactUseState < boolean > (false);

  function retrySearchNow() {
    shouldRetryNow((prev) => !prev);
  }

  function resetSearchNow() {
    shouldResetSearchNow((prev) => !prev);
  }

  useEffect(() => {
    const abortController = new AbortController();

    async function fetchData() {
      setErrorCode(ERROR_CODE_SEARCH_NO_ERROR);
      setDaftarPengguna([]);
      setTotalUsers(0);
      setIsLoaded(false);

      // construct query params
      const body = {
        page: currentPage,
        per_page: usersPerPage,
      }

      if(kataKunciPengguna) {
        Object.defineProperty(body, "usernamelike", {value: kataKunciPengguna, enumerable: true});
      }

      if(roleSaatIni) {
        Object.defineProperty(body, "role", {value: roleSaatIni, enumerable: true});
      }

      try {
        const response = await api.get('/api/list-users', {
          responseType: "json",
          signal: abortController.signal,
          params: body
        });

        const responseData = response.data as UserSearchResponse;

        setDaftarPengguna(responseData.data);
        setTotalUsers(responseData.total);
        setMaxPage(responseData.last_page);

        if (responseData.data.length === 0) {
          setErrorCode(ERROR_CODE_SEARCH_NO_DATA);
          return;
        }
        setIsLoaded(true);
      } catch (err) {
        if (axiosIsCancel(err)) {
          return;
        }

        if (isAxiosError(err)) {
          console.error(err);
          setErrorCode(ERROR_CODE_SEARCH_CONNECTION_ERROR);
        } else {
          console.error(err);
          setErrorCode(ERROR_CODE_SEARCH_UNKNOWN_ERROR);
        }
      }
    }
    fetchData();

    return () => {
      abortController.abort();
    };
  }, [retryNowTrigger, currentPage]);

  useEffect(function() {
    // pastikan kode tidak dijalankan pada tahap mounting
    if(searchHasMounted.current) {
      setIsLoaded(false);
      setCurrentPage(1);
      retrySearchNow();
    } else {
      searchHasMounted.current = true;
    }
  }, [roleSaatIni, usersPerPage, resetSearchTrigger]);

    return (<>
      <h1 className="scroll-m-20 text-2xl font-semibold tracking-tight lg:text-3xl m-5">Kelola Pengguna</h1>
      <Card className="m-5">
        <CardContent>
          <FieldGroup className="grid sm:grid-cols-1 lg:grid-cols-2 gap-4">
            <Field className="flex flex-col gap-2 w-full">
              <FieldLabel htmlFor="caripengguna">
                Cari Nama Pengguna:
              </FieldLabel>
              <div className="flex flex-col sm:flex-row items-stretch sm:items-center gap-2 w-full">
                <Input 
                  type="text" 
                  name="caripengguna" 
                  className="w-full sm:flex-1 min-w-0"
                  onChange={(e) => setKataKunciPengguna(e.target.value)} 
                  placeholder="Masukkan nama pengguna di sini" 
                  onKeyUp={(e) => (e.key === 'Enter' || e.keyCode === 13) && resetSearchNow()} 
                />
                <Button 
                  onClick={resetSearchNow} 
                  className="w-full sm:w-auto shrink-0 px-4"
                >
                  <Search className="w-5 h-5" />
                  Cari
                </Button>
              </div>
            </Field>
            <Field>
              <FieldLabel htmlFor="jenislaporan">Role pengguna:</FieldLabel>
              <NativeSelect required={false} name="role" onChange={(event) => { setRoleSaatIni(event.target.value) }}>
                  <NativeSelectOption tabIndex={1} value="" key={1}>Apapun</NativeSelectOption>
                  {
                    userRoles.map((value, index) => (
                      <NativeSelectOption tabIndex={index + 2} value={value.value} key={index}>{value.label}</NativeSelectOption>
                    ))
                  }
              </NativeSelect>
            </Field>
          </FieldGroup>
          <AddNewUserDialogFlow retryTrigger={retrySearchNow} buttonClassName="mt-3" />
        </CardContent>
      </Card>

      { errorCode === ERROR_CODE_SEARCH_CONNECTION_ERROR && 
        <NoConnectionCard className="m-5" retryTrigger={retrySearchNow} />
      }

      { errorCode === ERROR_CODE_SEARCH_NO_DATA && 
        <NoDataFoundCard className="m-5" />
      }

      { errorCode === ERROR_CODE_SEARCH_UNKNOWN_ERROR &&
        <UnknownErrorCard className="m-5" retryTrigger={retrySearchNow} />
      }

      { errorCode === ERROR_CODE_SEARCH_NO_ERROR && 
        <Card className="m-5">
          <CardContent>
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>No</TableHead>
                  <TableHead>ID</TableHead>
                  <TableHead>Nama Pengguna</TableHead>
                  <TableHead>Role</TableHead>
                  <TableHead>Aktif/Nonaktif</TableHead>
                  <TableHead>Tindakan</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                { isLoaded === true ?
                  daftarPengguna.map((user, index) => (
                    <TableRow key={index}>
                      <TableCell>{(usersPerPage * (currentPage - 1) + 1) + index}</TableCell>
                      <TableCell>{user.id}</TableCell>
                      <TableCell>{user.username}</TableCell>
                      <TableCell>{mapUserRole[user.role] ?? user.role}</TableCell>
                      <TableCell>{user.enabled? "Aktif" : "Nonaktif"}</TableCell>
                      <TableCell>
                        <div className="flex flex-row items-center gap-2">
                          <EditUserDialogFlow retryTrigger={retrySearchNow} currentUserInfo={user} />
                          {sessionManager.getIdentity()?.id !== user.id && 
                          <DeleteUserDialogFlow retryTrigger={retrySearchNow} deleteUserId={user.id} deleteUsername={user.username} />
                          }
                        </div>
                      </TableCell>
                    </TableRow>
                  ))
                :
                  Array.from({ length: usersPerPage }).map((_, index) => (
                    <TableRow key={index}>
                      <TableCell><Skeleton className="h-5 min-w-5" /></TableCell>
                      <TableCell><Skeleton className="h-5 min-w-10" /></TableCell>
                      <TableCell><Skeleton className="h-5 min-w-30" /></TableCell>
                      <TableCell><Skeleton className="h-5 min-w-30" /></TableCell>
                      <TableCell><Skeleton className="h-5 min-w-30" /></TableCell>
                      <TableCell><Skeleton className="h-5 min-w-30" /></TableCell>
                    </TableRow>
                  ))
                }
              </TableBody>
            </Table>
          </CardContent>
        </Card>
      }

      { (errorCode === ERROR_CODE_SEARCH_NO_ERROR || errorCode === ERROR_CODE_SEARCH_NO_DATA || errorCode === ERROR_CODE_SEARCH_CONNECTION_ERROR) &&
        <Card className="m-5">
          <CardContent>
            { (errorCode !== ERROR_CODE_SEARCH_NO_DATA || totalUsers > 0) &&
              <div>
                <div className="flex flex-col sm:flex-row items-center justify-center gap-2 w-full max-w-full px-4 box-border">

                  {currentPage - 1 > 0 && (
                    <Button onClick={() => setCurrentPage(currentPage - 1)} className="w-full sm:w-auto max-w-xs flex items-center justify-center gap-2">
                      <ArrowLeft />
                      <span>Sebelumnya</span>
                    </Button>
                  )}

                  <div className="flex flex-wrap items-center justify-center gap-1.5 max-w-full">
                    {currentPage - 2 >= 1 && (
                      <Button onClick={() => setCurrentPage(1)}>{1}</Button>
                    )}
                    {currentPage - 3 >= 1 && <div className="px-1">...</div>}
                    {currentPage - 1 > 0 && (
                      <Button onClick={() => setCurrentPage(currentPage - 1)}>
                        {currentPage - 1}
                      </Button>
                    )}

                    <Button disabled={true}>{currentPage}</Button>

                    {currentPage + 1 <= maxPage && (
                      <Button onClick={() => setCurrentPage(currentPage + 1)}>
                        {currentPage + 1}
                      </Button>
                    )}
                    {currentPage + 3 <= maxPage && <div className="px-1">...</div>}
                    {currentPage + 2 <= maxPage && (
                      <Button onClick={() => setCurrentPage(maxPage)}>{maxPage}</Button>
                    )}
                  </div>

                  {currentPage + 1 <= maxPage && (
                    <Button onClick={() => setCurrentPage(currentPage + 1)} className="w-full sm:w-auto max-w-xs flex items-center justify-center gap-2">
                      <span>Selanjutnya</span>
                      <ArrowRight />
                    </Button>
                  )}
                </div>
              </div>
            }

            <div className="flex flex-col sm:flex-row justify-between items-center gap-4">
              <div>
                { (errorCode !== ERROR_CODE_SEARCH_NO_DATA && totalUsers > 0)
                  ? `Menampilkan pengguna ${usersPerPage * (currentPage - 1) + 1}-${(usersPerPage * (currentPage - 1)) + daftarPengguna.length} dari ${totalUsers}`
                  : '\u00A0'}
              </div>
              <div className="flex items-center gap-2">
                <div>Pengguna per halaman</div>
                <NativeSelect defaultValue={10} name="jenislaporan" onChange={(event) => setUsersPerPage(parseInt(event.target.value)) }>
                  {
                    [10, 20, 50].map((value, index) => (
                      <NativeSelectOption tabIndex={index + 1} value={value} key={index}>{value}</NativeSelectOption>
                    ))
                  }
                </NativeSelect>
              </div>
            </div>
          </CardContent>
        </Card>
      }
    </>);
}

export default KelolaPengguna;
