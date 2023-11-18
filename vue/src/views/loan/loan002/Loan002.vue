<template src="./Loan002.html"></template>

<script lang="ts">
import { defineComponent } from "vue";
import Client from "../../../services/client";
import { ToastService } from '../../../services/toast';

const toastService = new ToastService();

export default defineComponent({
    name: "Loan002",
    data() {
        const customer = [] as any;
        let mDate = new Date();
        mDate.setFullYear(mDate.getFullYear() + 1);
        return {
            customer,
            customer_selected_id: "",
            borrow_type: "01",
            mDate: mDate.toISOString().slice(0, 10),
            nDate: new Date().toISOString().slice(0, 10),
            remark: "",
            loan_amount: 0,
            // firstInterestPaymentDate: "20230901",
            taxRate: 0,
            newTaxRate: 0,
            newTaxRateFromDate: "",
            newTaxRateToDate: "",
            borrowingInterestCalculationTypeCode: "01",
            borrowingPrinciplePaymentTypeCode: "01",
            payType: "15",
            currency: "USD",
            loading: false,
            applyInterestRate: 0.0,
            customerId: "",
            isGeneral: 0,
            // numofmonth:0,
        };
    },
    methods: {
        GetAllCustomer() {
            Client.get("/allcustomers").then((res: any) => {
                this.customer = res.data;
            });
        },
        GetCustomer() {
            let cusIndex = parseInt(this.customer_selected_id);
            const cus = this.customer[cusIndex];
            this.customerId = cus.id;
            this.applyInterestRate = parseFloat(cus.customer_type_rate);
            this.isGeneral = cus.custypeid;
        },
        getMonthDifference(startDate: Date, endDate: Date) {
            return (
                endDate.getMonth() -
                startDate.getMonth() +
                12 * (endDate.getFullYear() - startDate.getFullYear())
            );
        },
        CreateLoan() {
            this.loading = true;
            let obj = {
                customerId: this.customerId.toString(),
                borrowingTypeCode: this.borrow_type,
                currencyCode: this.currency,
                loanAmount: parseFloat(this.loan_amount.toString()),
                maturityDate: this.mDate.toString().replace(/-/g, ""),
                remarkDesc: this.remark,
                applyInterestRate: this.applyInterestRate,
                borrowingPrinciplePaymentTypeCode:
                    this.borrowingPrinciplePaymentTypeCode,
                newDate: this.nDate.toString().replace(/-/g, ""),
                firstInterestPaymentDate: this.nDate
                    .toString()
                    .replace(/-/g, ""),
                taxRate: this.taxRate,
                newTaxRate: this.newTaxRate,
                newTaxRateFromDate: this.newTaxRateFromDate,
                newTaxRateToDate: this.newTaxRateToDate,
                borrowingInterestCalculationTypeCode:
                    this.borrowingInterestCalculationTypeCode,
                payType: this.borrowingPrinciplePaymentTypeCode,
                numofmonth: this.getMonthDifference(
                    new Date(this.nDate.toString()),
                    new Date(this.mDate.toString())
                ),
            };

            if (this.customerId == "" || this.loan_amount == 0) {
                toastService.toastMessage('error','missing Some field')
                this.loading = false;
            } else {
                Client.post("/createborrower", obj)
                    .then(() => {
                        this.loading = false;
                        this.$router.push("/loan");
                        toastService.toastMessage('success','Success')
                    })
                    .catch((err) => {
                        toastService.toastMessage('error','Error', err)
                        this.loading = false;
                    });
            }
        },

        handleBack() {
            this.$router.push('/loan')
        },
    },
    created() {
        this.GetAllCustomer();
    },
});
</script>
