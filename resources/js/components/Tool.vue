<template>
    <loading-view :loading="isLoading">
        <heading :level="1" class="mb-3">{{ __('Profile') }}</heading>


        <card class="relative overflow-hidden mb-8">
            <component
                :class="{
                    'remove-bottom-border': index == fields.length - 1,
                }"
                v-for="(field, index) in fields"
                :key="index"
                :is="`form-${field.component}`"
                :errors="validationErrors"
                :field="field"
            />

            <div class="bg-30 flex px-8 py-4">
                <div class="ml-auto">
                    <button
                        @click="getFields"
                    >
                        {{ __('Reset') }}
                    </button>

                    <progress-button
                        @click.native="updateProfile"
                        :disabled="isWorking"
                        :processing="isWorking"
                        class="ml-2"
                    >
                        {{ __('Update Profile') }}
                    </progress-button>

                </div>
            </div>
        </card>
    </loading-view>
</template>

<script>
    import { Errors } from 'laravel-nova'

    export default {

        data: () => ({
            isLoading: false,
            isWorking: false,
            fields: [],
            validationErrors: new Errors(),
        }),

        created() {
            this.getFields()
        },

        methods: {

            /**
             * Get the available fields for the resource.
             */
            async getFields() {
                this.validationErrors = new Errors()

                this.isLoading = true

                this.fields = []

                const { data: fields } = await Nova.request().get(
                    '/nova-vendor/nova-profile-tool/'
                )

                this.fields = fields
                this.isLoading = false
            },

            /**
             * Saves the user's profile
             */
            async updateProfile() {
                try {
                    this.isWorking = true
                    const response = await this.createRequest()
                    this.isWorking = false

                    this.$toasted.show(
                        this.__('Your profile has been updated!'),
                        { type: 'success' }
                    )

                    // Reset the form by refetching the fields
                    this.getFields()

                    this.validationErrors = new Errors()
                } catch (error) {
                    this.isWorking = false
                    if (error.response.status == 422) {
                        this.validationErrors = new Errors(error.response.data.errors)
                        Nova.error(this.__('There was a problem submitting the form.'))
                    }
                }
            },

            /**
             * Send a create request to update the user's profile data
             */
            createRequest() {
                return Nova.request().post(
                    '/nova-vendor/nova-profile-tool/',
                    this.createResourceFormData()
                )
            },

            /**
             * Create the form data for creating the resource.
             */
            createResourceFormData() {
                return _.tap(new FormData(), formData => {
                    _.each(this.fields, field => {
                        field.fill(formData)
                    })
                })
            },
        },
    }
</script>
