<template>
    <div>
        <portal to="title">
			<app-title icon="paper-plane">Edit Form</app-title>
		</portal>

        <shared-form
            v-if="form"
            :form="form"
            :resource="resource">
        </shared-form>
    </div>
</template>

<script>
    import Form from '../../services/Form'
    import SharedForm from './SharedForm'

    export default {
        head: {
            title() {
                return {
                    inner: _.has(this.form, 'name') ? this.form.name : 'Loading...'
                }
            }
        },

        data() {
            return {
                id: null,
                resource: null,
                sections: [],
                form: null
            }
        },

        components: {
            'shared-form': SharedForm
        },

        watch: {
            sections: {
                deep: true,
                handler(value) {
                    if (! this.hasChanges) {
                        this.form.onFirstChange()
                    }
                }
            }
        },

        methods: {
            submit() {
                this.form.patch(`/api/forms/${this.id}`)
                    .then(() => {
                        axios.post(`/api/fieldsets/${this.resource.fieldset.id}/sections`, { sections: this.sections })
                            .then(() => {
                                toast('Form successfully saved', 'success')
                            })
                    }).catch((response) => {
                        toast(response.response.data.message, 'failed')
                    })
            }
        },

        beforeRouteEnter(to, from, next) {
            getForm(to.params.form, (error, form) => {
                if (error) {
                    next((vm) => {
                        vm.$router.push('/forms')

                        toast(error.toString(), 'danger')
                    })
                } else {
                    next((vm) => {
                        vm.id       = form.id
                        vm.resource = form
                        vm.sections = form.fieldset.sections

                        vm.form = new Form({
                            name:                    form.name,
                            handle:                  form.handle,
                            description:             form.description,
                            fieldset:                form.fieldset,
                            collect_email_addresses: form.collect_email_addresses,
                            collect_ip_addresses:    form.collect_ip_addresses,
                            response_receipt:        form.response_receipt,
                            message:                 form.message,
                            redirect_on_submission:  form.redirect_on_submission,
                            redirect_url:            form.redirect_url,
                            enable_recaptcha:        form.enable_recaptcha,
                            enable_honeypot:         form.enable_honeypot,
                            send_to:                 form.send_to,
                            reply_to:                form.reply_to,
                            form_template:           form.form_template,
                            thankyou_template:       form.thankyou_template,
                            status:                  form.status,
                        }, true)

                        vm.$nextTick(() => {
                            vm.$emit('updateHead')
                            vm.form.resetChangeListener()
                        })
                    })
                }
            })
        }
    }

    export function getForm(form, callback) {
        axios.get('/api/forms/' + form).then((response) => {
            callback(null, response.data.data)
        }).catch(function(error) {
            callback(new Error('The requested form could not be found'))
        })
    }
</script>