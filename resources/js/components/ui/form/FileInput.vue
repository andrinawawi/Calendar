<template>
    <div class="form-group">
        <global-layout :sizeForm="sizeForm">
            <label :for=inputName>{{ tekstLabel }}</label>
            <input :disabled=disabled type="file" class="form-control" :name=inputName :id=inputId  :value="value" @input="$emit('input', $event.target.value)" @change="loadTextFromFile"/>
            <div class="text-danger" v-if="errors">{{ errors[0] }}</div>
        </global-layout>
    </div>
</template>

<script>
    export default {
         props: {
             'tekstLabel' : String,
            'inputName' : String,
            'inputId' : String,
            'errors' : Array,
            'value' : String,
            'disabled': Boolean,
            'sizeForm': String,
         },

        data() {
            return {

            }
        },
    }
</script>
