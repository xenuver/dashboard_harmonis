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
  ...props
}, ref) => {
  const [file, setFile] = React.useState<File | null>(null)

  const handleDrop = React.useCallback(
    (acceptedFiles: File[], fileRejections: FileRejection[], event: DropEvent) => {
      if (acceptedFiles.length > 0) {
        setFile(acceptedFiles[0])
      }
      if (onDrop) {
        onDrop(acceptedFiles, fileRejections, event)
      }
    },
    [onDrop]
  )

  // Expose clear function to parent component
  React.useImperativeHandle(ref, () => ({
    clear: () => setFile(null),
  }))

  const { getRootProps, getInputProps, isDragActive } = useDropzone({
    onDrop: handleDrop,
    accept,
    multiple: false,
    ...props,
  })

  return (
    <div
      {...getRootProps()}
      className={cn(
        "flex flex-col items-center justify-center rounded-lg border-2 border-dashed p-8 text-center transition-colors hover:bg-muted/50 cursor-pointer min-h-45 w-full",
        isDragActive ? "border-primary bg-primary/10" : "border-muted-foreground/25",
        className
      )}
    >
      <input {...getInputProps()} />
      {icon}
      <p className="text-sm font-medium text-foreground">{label}</p>
      {description && <p className="text-xs text-muted-foreground mt-1">{description}</p>}
      
      {file && (
        <div className="mt-4 text-xs font-semibold text-primary">
          Selected: {file.name}
        </div>
      )}
    </div>
  )
})

Dropzone.displayName = "Dropzone"
export default Dropzone