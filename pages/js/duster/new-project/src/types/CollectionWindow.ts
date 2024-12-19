import type TimingConfig from "@/types/TimingConfig"
import type {TimingInterval} from "@/types/TimingConfig"
import type FieldMetadata from "@/types/FieldMetadata";
import {INIT_TIMING_CONFIG, INIT_TIMING_INTERVAL} from "@/types/TimingConfig";
import type TextValuePair from "@/types/TextValuePair";

interface CollectionWindow {
    label: string
    form_name: string
    type: CWTYPE
    timing_preset?: string | undefined
    timing_valid?: boolean | undefined
    timing: {
        start: TimingConfig
        end: TimingConfig
        repeat_interval: TimingInterval | undefined
    }
    aggregate_defaults?: Array<TextValuePair> | undefined
    event?: Array<TimingConfig>
    closest_time?: string
    data: {
        labs: Array<FieldMetadata>
        ud_labs: Array<FieldMetadata>
        vitals: Array<FieldMetadata>
        medications: Array<FieldMetadata>
        outcomes: Array<FieldMetadata>
        scores: Array<FieldMetadata>
        valid?: boolean
    }
    id?: string
}

export type CWTYPE ="nonrepeating" | "repeating"

export const INIT_COLLECTION_WINDOW: CollectionWindow = {
    label: "",
    form_name: "",
    type: "nonrepeating",
    timing_preset: undefined,
    timing_valid: false,
    timing: {
        start: JSON.parse(JSON.stringify(INIT_TIMING_CONFIG)),
        end: JSON.parse(JSON.stringify(INIT_TIMING_CONFIG)),
        repeat_interval: {...INIT_TIMING_INTERVAL}
    },
    aggregate_defaults: undefined,
    event: [JSON.parse(JSON.stringify(INIT_TIMING_CONFIG))],
    closest_time: "",
    data: {
        labs:[],
        ud_labs:[],
        vitals:[],
        medications:[],
        outcomes:[],
        scores:[],
        valid: false
    },
    id: "Undefined"
}

export default CollectionWindow;


