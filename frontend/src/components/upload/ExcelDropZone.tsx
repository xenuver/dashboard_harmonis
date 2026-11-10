import * as React from "react"
import { useDropzone, type DropzoneOptions, type FileRejection, type DropEvent } from "react-dropzone"
import { UploadCloud } from "lucide-react"
import { cn } from "@/lib/utils"

export interface ExcelDropzoneRef {
  clear: () => void
}

interface DropzoneProps extends DropzoneOptions {
  className?: string
  icon?: React.ReactNode
  label?: string
  description?: string
  disabled: boolean
  required: boolean
}

export const Dropzone = React.forwardRef<ExcelDropzoneRef, DropzoneProps>(({
  className,
  icon = <UploadCloud className="h-10 w-10 text-muted-foreground mb-2" />,
  label = "Drag & drop file di sini atau tekan untuk memilih file",
  description = "Hanya mendukung file .xlsx",
  onDrop,
  accept = {
    "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet": [".xlsx"]
  },
  required = false,
  ...props
}, ref) => {
  const [file, setFile] = React.useState<File | null>(null)
  const inputRef = React.useRef<HTMLInputElement>(null)
  const [filePickerErrorMessageText, setFilePickerErrorMessageText] = React.useState<string | null>(null)

  function clearDroppedFiles() {
    setFile(null)

    if (inputRef.current) {
      inputRef.current.value = ""
    }
  }

  function resetDropzone() {
    clearDroppedFiles()
    setFilePickerErrorMessageText(null)
  }

  const handleDrop = React.useCallback(
    (
      acceptedFiles: File[],
      fileRejections: FileRejection[],
      event: DropEvent
    ) => {
      setFilePickerErrorMessageText(null);
      if(acceptedFiles.length + fileRejections.length > 1) {
        setFilePickerErrorMessageText("Anda hanya dapat mengunggah maksimal 1 file");
        onDrop?.(acceptedFiles, fileRejections, event);
      }

      const selectedFile = acceptedFiles[0] ?? null
      const rejectedFile = fileRejections[0] ?? null

      setFile(selectedFile)

      if (selectedFile && inputRef.current) {
        setFilePickerErrorMessageText(null);
        const dataTransfer = new DataTransfer()
        dataTransfer.items.add(selectedFile)

        inputRef.current.files = dataTransfer.files
      } else if (rejectedFile && inputRef.current) {
        setFilePickerErrorMessageText(`Anda hanya dapat mengunggah file .xlsx (file dipilih: ${rejectedFile.file.name})`);
        clearDroppedFiles();
      }

      onDrop?.(acceptedFiles, fileRejections, event)
    },
    [onDrop]
  )

  React.useImperativeHandle(ref, () => ({
    clear: () => resetDropzone(),
  }))

  const {
    getRootProps, getInputProps, isDragActive} = useDropzone({
    onDrop: handleDrop,
    accept,
    multiple: false,
    ...props,
  })

  const inputProps = getInputProps({
    required,
  })  as React.InputHTMLAttributes<HTMLInputElement> & {
    ref?: React.Ref<HTMLInputElement>
  }

  const { ref: dropzoneRef, ...restInputProps } = inputProps

  const setInputRef = (node: HTMLInputElement | null) => {
    inputRef.current = node

    if (typeof dropzoneRef === "function") {
      dropzoneRef(node)
    } else if (dropzoneRef) {
      dropzoneRef.current = node
    }
  }

  return (
    <div
      {...getRootProps()}
      className={cn(
        "flex flex-col items-center justify-center rounded-lg border-2 border-dashed p-8 text-center transition-colors hover:bg-muted/50 cursor-pointer min-h-45 w-full",
        isDragActive ? "border-primary bg-primary/10" : "border-muted-foreground/25",
        className
      )}
    >
      <input {...restInputProps} onClick={() => resetDropzone()} ref={setInputRef}/>
      {icon}
      <p className="text-sm font-medium text-foreground">{label}</p>
      {description && (
        <p className="text-xs text-muted-foreground mt-1">{description}</p>
      )}
      {file && (
        <div className="mt-4 text-xs font-semibold text-primary">Selected: {file.name}</div>
      )}
      {filePickerErrorMessageText && (
        <div className="mt-4 text-xs font-semibold text-red-600">{filePickerErrorMessageText}</div>
      )}
    </div>
  )
})

Dropzone.displayName = "Dropzone"
export default Dropzone