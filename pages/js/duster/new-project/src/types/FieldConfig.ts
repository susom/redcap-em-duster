import type TimingConfig from "@/types/TimingConfig"
import {INIT_TIMING_CONFIG} from "@/types/TimingConfig";
import type TextValuePair from "@/types/TextValuePair";


export interface BasicConfig {
    label: string |  undefined
    value_type?: string
    redcap_field_name?: string
    redcap_field_type: string |  undefined
    phi?: string
    id?: string
    duster_field_name: string | undefined
    redcap_field_note?: string
    redcap_options?: string
    edit?: boolean
}

interface FieldConfig extends BasicConfig {
    aggregate?: string
    aggregate_options?: AggregateOptions
}

interface AggregateOptions {
    time?: string
    event?: TimingConfig
}

export type AGGREGATE_OPTION = "min_agg" | "max_agg" | "first_agg" | "last_agg" | "closest_event" | "closest_time"
export const AGGREGATE_OPTIONS: Array<TextValuePair> =[
    {text: "Min", value: "min_agg"},
    {text: "Max", value: "max_agg"},
    {text: "First", value: "first_agg"},
    {text: "Last", value: "last_agg"},
    {text: "Closest to Time", value: "closest_time"},
    {text: "Closest to Event", value: "closest_event"}
]

export const INIT_BASIC_CONFIG: BasicConfig = {
    duster_field_name: undefined,
    redcap_field_name: undefined,
    label: undefined,
    phi: undefined,
    value_type: undefined,
    redcap_field_type: undefined,
    id: undefined
}

export const INIT_FIELD_CONFIG: FieldConfig = {
    duster_field_name: undefined,
    redcap_field_name: undefined,
    label: undefined,
    phi: undefined,
    value_type: undefined,
    redcap_field_type: undefined,
    aggregate: undefined,
    aggregate_options: undefined,
    id: undefined
}

export default FieldConfig;
