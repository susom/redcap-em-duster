import type Subscore from "@/types/Subscore"
import type TextValuePair from "@/types/TextValuePair";

interface FieldMetadata {
    label: string
    duster_field_name: string
    category: string
    phi?: string
    value_type?: string
    redcap_field_type?: string
    //redcap_field_name?: string
    redcap_field_note?: string
    redcap_options?: string
    subscores?: Array<Subscore> | null
    aggregate_type?: AGGREGATE_TYPE
    aggregates?: Array<TextValuePair>
    selected?: boolean
    visible?: boolean
    info_status?: string
    description?: string
    description_short?: string
    loinc_code?: string
    unit?: string
    note?: string
    related?: string
    mdcalc?: string
    pubmed?: string
    edit?: boolean
}

export type AGGREGATE_TYPE = "default" | "custom"
export type VALUE_TYPE = "numeric" | "text" | "date" | "datetime" | "boolean" | "coded_value"
export type REDCAP_FIELD_TYPE = "text" | "calc" | "radio"
export type PHI = "t" | "f" | ""
export type CATEGORY = "demographics" | "labs" | "vitals" | "scores" | "outcomes" | "clinical_dates"

export default FieldMetadata;
