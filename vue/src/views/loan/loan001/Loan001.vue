<template src="./Loan001.html"></template>

<script lang="ts">
import { defineComponent } from "vue";
import { RequestService } from "../../../services/request-service";
import Loading from "../../../components/Loading.vue";

const requestService = new RequestService();

export default defineComponent({
    name: "Loan001",
    components: {
        Loading,
    },
    data() {
        const customerBorrowList = [] as any;
        const dataTable = [] as any[];
        return {
            customer_name: "",
            customerBorrowList,
            isLoading: false,
            dataTable,
            countOfPage: 10,
            currPage: 1,
            searchBy: ["customer_name"],
            search: "",
        };
    },
    created() {
        this.inquiryCustomerType();
    },
    computed: {
        headers() {
            return [
                { label: "ID." },
                { label: "Customer Name" },
                { label: "Start Date" },
                { label: "End Date" },
                { label: "Payment Type" },
                { label: "Currency Code" },
                { label: "Loan Amount" },
                { label: "Interest Rate" },
                { label: "Action" },
            ];
        },

        pageStart() {
            return (this.currPage - 1) * this.countOfPage;
        },
        totalPage() {
            return Math.ceil(this.customerBorrowList.length / this.countOfPage);
        },
        dataGrid() {
            if (this.search.length == 0) {
                this.dataTable = this.customerBorrowList;
            }
            this.dataTable = this.customerBorrowList.filter(
                (data: any) =>
                    !this.search ||
                    this.searchBy.some((item: any) =>
                        data[item]
                            .toString()
                            .toLowerCase()
                            .includes(this.search.toLowerCase())
                    )
            );
            return this.dataTable;
        },
    },
    methods: {
        async inquiryCustomerType() {
            this.isLoading = true;
            const res = await requestService.list(`/retriveborrower`);
            if (res.status === 200) {
                this.customerBorrowList = this.mapData(res);
            }
            this.isLoading = false;
        },
        mapData(list: any) {
            return list.data.map((item: any) => {
                return {
                    id: item.id,
                    customer_id: item.customer_id,
                    customer_name: item.customer_name,
                    startDate: item.startDate,
                    maturitydate: item.maturitydate,
                    paytype: item.payType,
                    currencycode: item.currencycode,
                    loanamount: item.loanamount,
                    applyinterestrate: item.applyinterestrate,
                    ispaid: item.ispaid,
                };
            });
        },

        getLoanSchedules: function (_id: string, _loandId: string) {
            localStorage.setItem("_id", _id);
            localStorage.setItem("_loandId", _loandId);
            this.$router.push(`/loan/schedules`);
        },

        getLoanSchedulesPrint: function (_id: string, _loandId: string) {
            localStorage.setItem("_id", _id);
            localStorage.setItem("_loandId", _loandId);
            let route = this.$router.resolve({ path: "/loan/print" });
            window.open(route.href, "_blank");
            //window.open("/loan/print", "_blank");
        },

        register() {
            this.$router.push("/loan/create");
        },

        setPage(data: any) {
            if (data <= 0 || data > this.totalPage) {
                return;
            }
            this.currPage = data;
        },
    },
});
</script>
