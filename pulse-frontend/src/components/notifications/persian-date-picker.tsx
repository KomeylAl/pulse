"use client";

import { useEffect, useState } from "react";
import DatePicker, { DateObject } from "react-multi-date-picker";
import TimePicker from "react-multi-date-picker/plugins/time_picker";
import persian from "react-date-object/calendars/persian";
import persian_fa from "react-date-object/locales/persian_fa";

import { Label } from "@/components/ui/input";
import { cn } from "@/lib/utils";

const inputClass =
  "flex h-9 w-full rounded-lg border border-input bg-background px-3 py-1 text-sm outline-none transition focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50";

function toPersianObject(value: Date | null) {
  if (!value) return null;
  return new DateObject({ date: value, calendar: persian, locale: persian_fa });
}

function fromPicker(date: DateObject | DateObject[] | null): Date | null {
  if (!date) return null;
  const selected = Array.isArray(date) ? date[0] : date;
  return selected?.toDate?.() ?? null;
}

export function PersianDateTimePicker({
  value,
  onChange,
  label = "تاریخ و ساعت",
  placeholder = "انتخاب تاریخ و ساعت",
  hint,
  className,
}: {
  value: Date | null;
  onChange: (value: Date | null) => void;
  label?: string;
  placeholder?: string;
  hint?: string;
  className?: string;
}) {
  const [internal, setInternal] = useState<DateObject | null>(null);

  useEffect(() => {
    setInternal(toPersianObject(value));
  }, [value]);

  return (
    <div className={cn("space-y-2", className)}>
      {label && <Label>{label}</Label>}
      <DatePicker
        value={internal}
        onChange={(date) => {
          const selected = Array.isArray(date) ? date[0] : date;
          setInternal(selected ?? null);
          onChange(fromPicker(date));
        }}
        calendar={persian}
        locale={persian_fa}
        format="YYYY/MM/DD HH:mm"
        plugins={[<TimePicker key="time" hideSeconds position="bottom" />]}
        inputClass={inputClass}
        containerClassName="w-full"
        calendarPosition="bottom-right"
        editable={false}
        placeholder={placeholder}
        className="pulse-datepicker"
      />
      {hint && <p className="text-xs text-muted-foreground">{hint}</p>}
    </div>
  );
}

export function PersianDatePicker({
  value,
  onChange,
  label = "تاریخ",
  placeholder = "انتخاب تاریخ",
  className,
}: {
  value: Date | null;
  onChange: (value: Date | null) => void;
  label?: string;
  placeholder?: string;
  className?: string;
}) {
  const [internal, setInternal] = useState<DateObject | null>(null);

  useEffect(() => {
    setInternal(toPersianObject(value));
  }, [value]);

  return (
    <div className={cn("space-y-2", className)}>
      {label && <Label>{label}</Label>}
      <DatePicker
        value={internal}
        onChange={(date) => {
          const selected = Array.isArray(date) ? date[0] : date;
          setInternal(selected ?? null);
          onChange(fromPicker(date));
        }}
        calendar={persian}
        locale={persian_fa}
        format="YYYY/MM/DD"
        inputClass={inputClass}
        containerClassName="w-full"
        calendarPosition="bottom-right"
        editable={false}
        placeholder={placeholder}
        className="pulse-datepicker"
      />
    </div>
  );
}

export function PersianMultiDatePicker({
  values,
  onChange,
  label = "انتخاب روزها",
  placeholder = "چند تاریخ از تقویم انتخاب کنید",
  className,
}: {
  values: Date[];
  onChange: (values: Date[]) => void;
  label?: string;
  placeholder?: string;
  className?: string;
}) {
  const [internal, setInternal] = useState<DateObject[]>([]);

  useEffect(() => {
    setInternal(
      values.map(
        (date) => new DateObject({ date, calendar: persian, locale: persian_fa }),
      ),
    );
  }, [values]);

  return (
    <div className={cn("space-y-2", className)}>
      {label && <Label>{label}</Label>}
      <DatePicker
        multiple
        value={internal}
        onChange={(dates) => {
          const list = Array.isArray(dates) ? dates : dates ? [dates] : [];
          setInternal(list);
          onChange(list.map((item) => item.toDate()).filter(Boolean));
        }}
        calendar={persian}
        locale={persian_fa}
        format="YYYY/MM/DD"
        inputClass={inputClass}
        containerClassName="w-full"
        calendarPosition="bottom-right"
        editable={false}
        placeholder={placeholder}
        className="pulse-datepicker"
      />
      {values.length > 0 && (
        <p className="text-xs text-muted-foreground">
          {values.length.toLocaleString("fa-IR")} روز انتخاب شده
        </p>
      )}
    </div>
  );
}

export function PersianTimeInput({
  value,
  onChange,
  label = "ساعت",
  className,
}: {
  value: string;
  onChange: (value: string) => void;
  label?: string;
  className?: string;
}) {
  return (
    <div className={cn("space-y-2", className)}>
      {label && <Label>{label}</Label>}
      <input
        type="time"
        value={value}
        onChange={(event) => onChange(event.target.value)}
        className={inputClass}
        dir="ltr"
      />
    </div>
  );
}

export function formatGregorianDate(date: Date): string {
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, "0");
  const day = String(date.getDate()).padStart(2, "0");
  return `${year}-${month}-${day}`;
}

export function parseGregorianDate(value: string): Date {
  const [year, month, day] = value.split("-").map(Number);
  return new Date(year, (month || 1) - 1, day || 1);
}

export function formatPersianDateLabel(date: Date | string): string {
  const jsDate = typeof date === "string" ? parseGregorianDate(date) : date;
  return new DateObject({
    date: jsDate,
    calendar: persian,
    locale: persian_fa,
  }).format("YYYY/MM/DD");
}
